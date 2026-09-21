<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 予約済み面談の事前リマインダー(前日 / 開始1時間前)を、面談の当事者(受講生・コーチ)双方へ通知する。
 * キュー化はしない(同期送信)。
 */
class MeetingReminderNotification extends Notification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
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
            'notification_type' => 'meeting_reminder',
            'title' => $this->title(),
            'message' => $this->body(),
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS - '.$this->title())
            ->greeting($notifiable->name.'様')
            ->line($this->body())
            ->action('面談詳細を確認する', route('meetings.show', $this->meeting))
            ->salutation('Certify LMS 運営チーム');
    }

    private function title(): string
    {
        return match ($this->window) {
            MeetingReminderWindow::Eve => '明日の面談のリマインダー',
            MeetingReminderWindow::OneHourBefore => 'まもなく面談が始まります',
        };
    }

    private function body(): string
    {
        $datetime = $this->meeting->scheduled_at->format('Y/m/d H:i');

        return match ($this->window) {
            MeetingReminderWindow::Eve => "明日 {$datetime} に面談が予定されています。",
            MeetingReminderWindow::OneHourBefore => "{$datetime} に面談が開始します(1時間前のお知らせ)。",
        };
    }
}
