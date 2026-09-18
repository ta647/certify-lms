<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\MeetingReserved;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 面談予約イベント → 担当コーチへの通知発火を検証する(受講生本人には発火しない)。
 */
class SendMeetingReservedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_is_notified_but_student_is_not(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->forStudent($student)->forCoach($coach)->create();

        event(new MeetingReserved($meeting));

        Notification::assertSentTo($coach, MeetingReservedNotification::class);
        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
    }

    public function test_withdrawn_coach_is_not_notified(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->withdrawn()->create();
        $meeting = Meeting::factory()->forStudent($student)->forCoach($coach)->create();

        event(new MeetingReserved($meeting));

        Notification::assertNothingSent();
    }
}
