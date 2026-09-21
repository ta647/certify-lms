<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_targets_only_tomorrows_meetings(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $tomorrow = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);
        $today = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addHours(3),
        ]);
        $dayAfterTomorrow = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        ]);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])->assertExitCode(0);

        $this->assertNotNull($tomorrow->fresh()->eve_reminder_sent_at);
        $this->assertNull($today->fresh()->eve_reminder_sent_at);
        $this->assertNull($dayAfterTomorrow->fresh()->eve_reminder_sent_at);
        Notification::assertSentToTimes($student, MeetingReminderNotification::class, 1);
    }

    public function test_one_hour_before_window_targets_only_meetings_within_65_minutes(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $soon = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(30),
        ]);
        $farAway = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addHours(5),
        ]);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])->assertExitCode(0);

        $this->assertNotNull($soon->fresh()->one_hour_reminder_sent_at);
        $this->assertNull($farAway->fresh()->one_hour_reminder_sent_at);
        Notification::assertSentToTimes($student, MeetingReminderNotification::class, 1);
    }

    public function test_invalid_window_fails_without_sending(): void
    {
        Notification::fake();

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'bogus'])->assertExitCode(1);

        Notification::assertNothingSent();
    }
}
