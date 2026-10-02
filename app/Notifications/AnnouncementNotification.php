<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Concerns\RetriesTemporaryFailures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 管理者お知らせの配信通知。遷移先の業務画面を持たない自己完結型のため、`url`は通知詳細ページ
 * (`notifications.show`)を指す。
 *
 * キュー化(T-A-05): 一斉配信は対象受講生の人数分 Notification::send() がループするため、
 * 同期送信だと人数に比例してリクエストがブロックされる。キュー化し worker に処理を委ねる。
 */
class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable, RetriesTemporaryFailures;

    public function __construct(private readonly Announcement $announcement) {}

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
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => Str::limit($this->announcement->body, 100),
            'url' => route('notifications.show', $this->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Certify LMS からのお知らせ: '.$this->announcement->title)
            ->greeting($notifiable->name.'様')
            ->line($this->announcement->title)
            ->line($this->announcement->body)
            ->salutation('Certify LMS 運営チーム');
    }
}
