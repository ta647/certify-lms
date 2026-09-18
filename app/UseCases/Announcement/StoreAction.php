<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * 管理者お知らせの新規配信ユースケース。
 *
 * 配信対象は常に「role=student かつ status=in_progress」に絞り込まれ(コーチ/管理者/対象外状態は
 * 配信対象から除外)、資格指定の場合はさらに指定資格に status=learning で受講登録していることを要求する。
 * 対象が0名でも配信自体は成立し、dispatched_count=0 のまま履歴に残す(誤操作の証跡として残す仕様)。
 */
final class StoreAction
{
    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $recipients = $this->resolveRecipients($validated);

        $announcement = DB::transaction(fn () => Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_type' => $validated['target_type'],
            'target_certification_id' => $validated['target_certification_id'] ?? null,
            'target_user_id' => $validated['target_user_id'] ?? null,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now(),
            'created_by_user_id' => $admin->id,
        ]));

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AnnouncementNotification($announcement));
        }

        return $announcement;
    }

    /**
     * @param array{target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(array $validated): Collection
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);

        return match ($targetType) {
            AnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->get(),

            AnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->whereHas('enrollments', function ($query) use ($validated) {
                    $query->where('certification_id', $validated['target_certification_id'])
                        ->where('status', EnrollmentStatus::Learning->value);
                })
                ->get(),

            AnnouncementTargetType::User => User::query()
                ->where('id', $validated['target_user_id'])
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->get(),
        };
    }
}
