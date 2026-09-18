<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * チャットメッセージ送信 → 送信者以外のルームメンバーへの通知発火を検証する。
 */
class SendChatMessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_member_is_notified_but_sender_is_not(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $certification->id, 'user_id' => $coach->id]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => 'こんにちは']);

        Notification::assertSentTo($coach, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_withdrawn_member_is_not_notified(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $withdrawnCoach = User::factory()->coach()->withdrawn()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $certification->id, 'user_id' => $withdrawnCoach->id]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $withdrawnCoach->id]);

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => 'こんにちは']);

        Notification::assertNotSentTo($withdrawnCoach, ChatMessageReceivedNotification::class);
    }
}
