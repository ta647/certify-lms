<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Events\MeetingReserved;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の予約申請ユースケース。残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して
 * reserved で確定する。同時刻 race condition は (coach_id, scheduled_at) UNIQUE 違反として検知し
 * MeetingNoAvailableCoachException(→Controller で 409 相当)へ変換する。
 *
 * 面談回数の消費・MeetingReserved イベント発火(通知送信 + Googleカレンダー連動)は、予約確定と
 * 同一トランザクション境界で行う。
 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly GoogleCalendarService $googleCalendar,
    ) {}

    /**
     * @throws InsufficientMeetingQuotaException
     * @throws MeetingNoAvailableCoachException
     */
    public function __invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting
    {
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $topic) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // 同時刻に他受講生が先行予約した race condition: UNIQUE(coach_id, scheduled_at) で弾かれた
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            $meeting = $meeting->fresh();

            event(new MeetingReserved($meeting));

            return $meeting;
        });
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、
     * (2) 当該時刻に reserved / completed の Meeting を持たず、
     * (3) Googleカレンダー連携済の場合は当該時刻がGoogle上busyでないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');
        $slotKey = $scheduledAt->format('H:i');

        $candidates = $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->with('googleCredential')
            ->get();

        return $candidates
            ->reject(fn (User $coach) => in_array(
                $slotKey,
                $this->googleCalendar->busyTimeKeysForCoach($coach, $scheduledAt->copy()->startOfDay()),
                true,
            ))
            ->values();
    }
}
