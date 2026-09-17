<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談が予約されたことを担当コーチへ通知する(受講生本人には発火しない)。
 * キュー化はしない(同期送信)。
 */
class MeetingReservedNotification extends Notification
{
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
        $this->meeting->loadMissing('student');

        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談予約が入りました',
            'message' => $this->meeting->student->name.'さんとの面談が '.$this->meeting->scheduled_at->format('Y/m/d H:i').' に予約されました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->meeting->loadMissing('student');

        return (new MailMessage)
            ->subject('Certify LMS - 面談予約が入りました')
            ->greeting($notifiable->name.'様')
            ->line($this->meeting->student->name.'さんとの面談が予約されました。')
            ->line('日時: '.$this->meeting->scheduled_at->format('Y/m/d H:i'))
            ->action('面談詳細を確認する', route('meetings.show', $this->meeting))
            ->salutation('Certify LMS 運営チーム');
    }
}
