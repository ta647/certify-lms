<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingReminderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_student_and_coach_and_marks_sent(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(SendMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
        $this->assertNotNull($meeting->fresh()->eve_reminder_sent_at);
    }

    public function test_does_not_notify_twice_for_same_window(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        $action = app(SendMeetingReminderAction::class);
        $action($meeting, MeetingReminderWindow::OneHourBefore);
        $action($meeting->fresh(), MeetingReminderWindow::OneHourBefore);

        Notification::assertSentToTimes($student, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($coach, MeetingReminderNotification::class, 1);
    }

    public function test_skips_canceled_meeting(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->canceled()->forStudent($student)->forCoach($coach)->create();

        app(SendMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        Notification::assertNothingSent();
        $this->assertNull($meeting->fresh()->eve_reminder_sent_at);
    }

    public function test_skips_ineligible_recipient_but_notifies_the_other(): void
    {
        Notification::fake();

        $student = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(SendMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        Notification::assertNotSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }
}
