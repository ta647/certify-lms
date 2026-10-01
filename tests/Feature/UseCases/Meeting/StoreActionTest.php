<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Events\MeetingReserved;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_creates_reserved_meeting_and_fires_event_in_same_transaction(): void
    {
        Event::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $meeting = app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
        ]);
        Event::assertDispatched(MeetingReserved::class);
    }

    public function test_throws_when_certification_has_no_coach(): void
    {
        // 担当コーチが 1 人も居ない資格は、空き枠自体が存在しないため validateSlot で弾かれる
        // (findAvailableCoaches の候補空チェックに到達する前段)。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->expectException(MeetingOutOfAvailabilityException::class);

        app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');
    }

    public function test_rejects_slot_busy_on_connected_coachs_google_calendar(): void
    {
        // 唯一の担当コーチがGoogle上busyだと、空き枠集計(validateSlot)の段階で
        // 対象スロットの available_coach_count が 0 になり弾かれる。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('busyTimeKeysForCoach')->andReturn(['10:00']);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->expectException(MeetingOutOfAvailabilityException::class);

        app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');
    }

    public function test_blocks_double_booking_for_same_coach_and_slot(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $otherStudent = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        Meeting::factory()->canceled()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => $scheduledAt,
        ]);

        $this->expectException(MeetingNoAvailableCoachException::class);

        app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');
    }
}
