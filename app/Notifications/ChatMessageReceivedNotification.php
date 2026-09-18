<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * チャットルームに新しいメッセージが投稿されたことを、送信者以外のルームメンバーへ通知する。
 * キュー化はしない(同期送信)。
 */
class ChatMessageReceivedNotification extends Notification
{
    public function __construct(private readonly ChatMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array{notification_type: string, title: string, message: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        $this->message->loadMissing('sender');

        return [
            'notification_type' => 'chat_message_received',
            'title' => $this->message->sender->name.'さんからメッセージが届きました',
            'message' => Str::limit($this->message->body, 100),
            'url' => route('chat.show', $this->message->chat_room_id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->message->loadMissing('sender');

        return (new MailMessage)
            ->subject('Certify LMS - 新しいチャットメッセージが届きました')
            ->greeting($notifiable->name.'様')
            ->line($this->message->sender->name.'さんから新しいメッセージが届きました。')
            ->line(Str::limit($this->message->body, 100))
            ->action('チャットを確認する', route('chat.show', $this->message->chat_room_id))
            ->salutation('Certify LMS 運営チーム');
    }
}
