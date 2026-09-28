<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\MeetingReserved;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 面談予約イベント → Googleカレンダーへの予定作成を検証する。
 */
class SyncMeetingToGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_event_and_saves_id_for_connected_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create();
        $meeting = Meeting::factory()->forCoach($coach)->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('createEvent')
            ->once()
            ->with(Mockery::on(fn (Meeting $m) => $m->id === $meeting->id))
            ->andReturn('google-event-id-123');
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingReserved($meeting));

        $this->assertSame('google-event-id-123', $meeting->fresh()->google_calendar_event_id);
    }

    public function test_does_nothing_for_unconnected_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->forCoach($coach)->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldNotReceive('createEvent');
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingReserved($meeting));

        $this->assertNull($meeting->fresh()->google_calendar_event_id);
    }

    public function test_leaves_event_id_null_when_creation_fails(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create();
        $meeting = Meeting::factory()->forCoach($coach)->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('createEvent')->once()->andReturn(null);
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingReserved($meeting));

        $this->assertNull($meeting->fresh()->google_calendar_event_id);
    }
}
