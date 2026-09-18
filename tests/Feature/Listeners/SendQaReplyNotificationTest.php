<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 質問掲示板への回答投稿 → スレッド投稿者への通知発火を検証する(自己回答は対象外)。
 */
class SendQaReplyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_owner_is_notified_when_another_student_replies(): void
    {
        Notification::fake();

        $owner = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), ['body' => '参考になりました']);

        Notification::assertSentTo($owner, QaReplyReceivedNotification::class);
    }

    public function test_self_reply_does_not_notify(): void
    {
        Notification::fake();

        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('qa-board.replies.store', $thread), ['body' => '補足します']);

        Notification::assertNothingSent();
    }

    public function test_graduated_owner_is_not_notified(): void
    {
        Notification::fake();

        $owner = User::factory()->student()->graduated()->create();
        $replier = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), ['body' => '回答です']);

        Notification::assertNotSentTo($owner, QaReplyReceivedNotification::class);
    }
}
