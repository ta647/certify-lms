<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Console\Commands\Mentoring\SendMeetingRemindersCommand;
use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Notifications\MeetingReminderNotification;
use App\Services\NotificationRecipientFilter;
use Illuminate\Support\Facades\DB;

/**
 * Schedule Command から呼ばれる面談リマインダー送信ユースケース。
 *
 * 複数台 worker が並行起動して二重送信が走るのを防ぐため、`AutoCompleteMeetingAction` と同様に
 * `lockForUpdate()` で対象行を取得し直しトランザクション内で「予約済み」かつ「当該ウィンドウが未送信」
 * であることを再確認してから送信済みフラグを更新する(冪等)。通知の送信自体はトランザクション外で行う。
 *
 * @see SendMeetingRemindersCommand
 */
final class SendMeetingReminderAction
{
    public function __invoke(Meeting $meeting, MeetingReminderWindow $window): void
    {
        $locked = DB::transaction(function () use ($meeting, $window) {
            $row = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            $column = $window->sentAtColumn();

            if ($row === null || $row->status !== MeetingStatus::Reserved || $row->{$column} !== null) {
                return null;
            }

            $row->update([$column => now()]);

            return $row;
        });

        if ($locked === null) {
            return;
        }

        $locked->loadMissing(['student', 'coach']);

        foreach ([$locked->student, $locked->coach] as $recipient) {
            if ($recipient !== null && NotificationRecipientFilter::isEligible($recipient)) {
                $recipient->notify(new MeetingReminderNotification($locked, $window));
            }
        }
    }
}
