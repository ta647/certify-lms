<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Notifications\ChatMessageReceivedNotification;
use App\Services\NotificationRecipientFilter;
use Illuminate\Support\Facades\Notification;

/**
 * チャットメッセージ送信イベントを受けて、送信者以外の全ルームメンバーへ通知する。
 * キュー化はしない(同期送信)。
 */
final class SendChatMessageNotification
{
    public function handle(ChatMessageSent $event): void
    {
        $message = $event->message;

        $recipients = ChatMember::query()
            ->forRoom($message->chatRoom)
            ->where('user_id', '!=', $message->sender_user_id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($user) => $user !== null && NotificationRecipientFilter::isEligible($user));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ChatMessageReceivedNotification($message));
    }
}
