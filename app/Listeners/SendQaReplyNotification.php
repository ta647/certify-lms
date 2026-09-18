<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\QaReplyPosted;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationRecipientFilter;

/**
 * 質問掲示板への回答投稿イベントを受けて、スレッド投稿者へ通知する(自己回答時は発火しない)。
 * キュー化はしない(同期送信)。
 */
final class SendQaReplyNotification
{
    public function handle(QaReplyPosted $event): void
    {
        if ($event->reply->user_id === $event->thread->user_id) {
            return;
        }

        $recipient = $event->thread->loadMissing('user')->user;

        if ($recipient === null || ! NotificationRecipientFilter::isEligible($recipient)) {
            return;
        }

        $recipient->notify(new QaReplyReceivedNotification($event->thread, $event->reply));
    }
}
