<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\MeetingCanceled;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 面談キャンセルイベント → キャンセル実行者の相手方への通知発火を検証する(自己通知はしない)。
 */
class SendMeetingCanceledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_is_notified_when_student_cancels(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->forStudent($student)->forCoach($coach)->canceled()->create();

        event(new MeetingCanceled($meeting, $student));

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_student_is_notified_when_coach_cancels(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->forStudent($student)->forCoach($coach)->canceled()->create();

        event(new MeetingCanceled($meeting, $coach));

        Notification::assertSentTo($student, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }
}
