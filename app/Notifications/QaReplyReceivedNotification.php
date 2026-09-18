<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 質問掲示板のスレッドに新しい回答が投稿されたことを、投稿者へ通知する(自己回答時は発火しない)。
 * キュー化はしない(同期送信)。
 */
class QaReplyReceivedNotification extends Notification
{
    public function __construct(
        private readonly QaThread $thread,
        private readonly QaReply $reply,
    ) {}

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
        return [
            'notification_type' => 'qa_reply_received',
            'title' => '「'.$this->thread->title.'」に回答が届きました',
            'message' => Str::limit($this->reply->body, 100),
            'url' => route('qa-board.show', $this->thread),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS - 質問掲示板に回答が届きました')
            ->greeting($notifiable->name.'様')
            ->line('あなたの質問「'.$this->thread->title.'」に回答が届きました。')
            ->line(Str::limit($this->reply->body, 100))
            ->action('質問掲示板を確認する', route('qa-board.show', $this->thread))
            ->salutation('Certify LMS 運営チーム');
    }
}
