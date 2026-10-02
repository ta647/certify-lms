<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use App\Notifications\Concerns\RetriesTemporaryFailures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談がキャンセルされたことを、キャンセル実行者の相手方へ通知する(自己通知はしない)。
 * キュー化(T-A-05): 発火元リクエストをブロックしないよう worker に送信を委ねる。
 */
class MeetingCanceledNotification extends Notification implements ShouldQueue
{
    use Queueable, RetriesTemporaryFailures;

    public function __construct(private readonly Meeting $meeting) {}

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
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => $this->meeting->scheduled_at->format('Y/m/d H:i').' の面談がキャンセルされました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS - 面談がキャンセルされました')
            ->greeting($notifiable->name.'様')
            ->line($this->meeting->scheduled_at->format('Y/m/d H:i').' に予定されていた面談がキャンセルされました。')
            ->action('面談詳細を確認する', route('meetings.show', $this->meeting))
            ->salutation('Certify LMS 運営チーム');
    }
}
