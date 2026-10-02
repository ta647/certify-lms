<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Mail\InvitationMail;
use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Notifications\AnnouncementNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-A-05: 通知・メール送信の非同期化。
 *
 * 各通知/メールが ShouldQueue を実装し、共通のリトライ方針(最大2回、段階的バックオフ)を
 * 持つことを検証する。実際に worker に処理されるかどうかの end-to-end 検証は
 * tests/Feature/UseCases/Queue/QueuedDeliveryAfterCommitTest.php で扱う。
 */
class QueuedNotificationsRetryPolicyTest extends TestCase
{
    /**
     * @return array<string, array{0: object}>
     */
    public static function queueableProvider(): array
    {
        return [
            'AnnouncementNotification' => [new AnnouncementNotification(new Announcement)],
            'ChatMessageReceivedNotification' => [new ChatMessageReceivedNotification(new ChatMessage)],
            'MeetingCanceledNotification' => [new MeetingCanceledNotification(new Meeting)],
            'MeetingReservedNotification' => [new MeetingReservedNotification(new Meeting)],
            'QaReplyReceivedNotification' => [new QaReplyReceivedNotification(new QaThread, new QaReply)],
            'InvitationMail' => [new InvitationMail(new Invitation)],
        ];
    }

    #[DataProvider('queueableProvider')]
    public function test_implements_should_queue(object $instance): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $instance);
    }

    #[DataProvider('queueableProvider')]
    public function test_retries_up_to_three_times_with_staged_backoff(object $instance): void
    {
        $this->assertSame(3, $instance->tries, '計3回試行(初回 + 最大2回リトライ)のはず');
        $this->assertSame([30, 300], $instance->backoff(), '30秒後→5分後の段階的バックオフのはず');
    }

    public function test_meeting_reminder_notification_implements_should_queue(): void
    {
        // コンストラクタ引数が他クラスと異なる(enumを追加で取る)ためプロバイダから分離
        $instance = new MeetingReminderNotification(new Meeting, MeetingReminderWindow::Eve);

        $this->assertInstanceOf(ShouldQueue::class, $instance);
        $this->assertSame(3, $instance->tries);
        $this->assertSame([30, 300], $instance->backoff());
    }
}
