<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_stores_state_and_redirects_to_google(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('buildAuthUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/v2/auth?mocked=1');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', ['redirect_path' => '/settings/availability']));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?mocked=1');
        $this->assertNotNull(session('google_oauth_state'));
        $this->assertSame('/settings/availability', session('google_oauth_redirect_path'));
    }

    public function test_redirect_rejects_unsafe_redirect_path(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('buildAuthUrl')->once()->andReturn('https://accounts.google.com/mocked');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', ['redirect_path' => '//evil.example.com']));

        $this->assertSame('/settings/availability', session('google_oauth_redirect_path'));
    }

    public function test_student_cannot_access_redirect(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->withSession(['google_oauth_state' => 'expected-state', 'google_oauth_redirect_path' => '/settings/availability'])
            ->get(route('settings.google-calendar.callback', ['state' => 'tampered-state', 'code' => 'auth-code']));

        $response->assertRedirect('/settings/availability');
        $response->assertSessionHas('error');
        $this->assertNull($coach->fresh()->googleCredential);
    }

    public function test_callback_creates_credential_on_valid_state_and_code(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('exchangeCode')->once()->andReturn([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)
            ->withSession(['google_oauth_state' => 'matching-state', 'google_oauth_redirect_path' => '/settings/availability'])
            ->get(route('settings.google-calendar.callback', ['state' => 'matching-state', 'code' => 'auth-code']));

        $response->assertRedirect('/settings/availability');
        $response->assertSessionHas('success');

        $credential = $coach->fresh()->googleCredential;
        $this->assertNotNull($credential);
        $this->assertSame('new-access-token', $credential->access_token);
        $this->assertSame('new-refresh-token', $credential->refresh_token);
        $this->assertSame('primary', $credential->calendar_id);
    }

    public function test_callback_preserves_existing_refresh_token_when_omitted(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create(['refresh_token' => 'original-refresh-token']);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('exchangeCode')->once()->andReturn([
            'access_token' => 'rotated-access-token',
            'refresh_token' => null,
            'expires_in' => 3600,
        ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($coach)
            ->withSession(['google_oauth_state' => 'matching-state', 'google_oauth_redirect_path' => '/settings/availability'])
            ->get(route('settings.google-calendar.callback', ['state' => 'matching-state', 'code' => 'auth-code']));

        $this->assertSame('original-refresh-token', $coach->fresh()->googleCredential->refresh_token);
    }

    public function test_destroy_removes_own_credential(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create();

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success');
        $this->assertNull($coach->fresh()->googleCredential);
    }

    public function test_destroy_is_safe_when_not_connected(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.availability.index'));
    }
}
