<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\MeetingCanceled;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 面談キャンセルイベント → Googleカレンダー上の予定削除を検証する。
 */
class RemoveMeetingFromGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_event_for_connected_coach_with_event_id(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $credential = GoogleCalendarCredential::factory()->forUser($coach)->create();
        $meeting = Meeting::factory()->canceled()->forCoach($coach)->create([
            'google_calendar_event_id' => 'google-event-id-123',
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('deleteEvent')
            ->once()
            ->with(Mockery::on(fn (GoogleCalendarCredential $c) => $c->id === $credential->id), 'google-event-id-123');
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingCanceled($meeting, $meeting->student));

        // 例外が起きずdeleteEventが1回呼ばれることを確認済み(Mockeryのshould receive)
        $this->addToAssertionCount(1);
    }

    public function test_does_nothing_for_unconnected_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->canceled()->forCoach($coach)->create([
            'google_calendar_event_id' => 'google-event-id-123',
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldNotReceive('deleteEvent');
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingCanceled($meeting, $meeting->student));

        $this->addToAssertionCount(1);
    }

    public function test_does_nothing_when_no_event_was_ever_created(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create();
        $meeting = Meeting::factory()->canceled()->forCoach($coach)->create([
            'google_calendar_event_id' => null,
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldNotReceive('deleteEvent');
        $this->app->instance(GoogleCalendarService::class, $mock);

        event(new MeetingCanceled($meeting, $meeting->student));

        $this->addToAssertionCount(1);
    }
}
