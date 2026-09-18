<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Notifications\MeetingCanceledNotification;
use App\Services\NotificationRecipientFilter;

/**
 * 面談キャンセルイベントを受けて、キャンセル実行者の相手方へ通知する(自己通知はしない)。
 * キュー化はしない(同期送信)。
 */
final class SendMeetingCanceledNotification
{
    public function handle(MeetingCanceled $event): void
    {
        $meeting = $event->meeting->loadMissing(['student', 'coach']);

        $recipient = $event->actor->id === $meeting->student_id
            ? $meeting->coach
            : $meeting->student;

        if ($recipient === null || ! NotificationRecipientFilter::isEligible($recipient)) {
            return;
        }

        $recipient->notify(new MeetingCanceledNotification($meeting));
    }
}
