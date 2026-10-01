<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\GoogleCalendarTestHelpers;
use Tests\TestCase;

/**
 * GoogleCalendarService が Google Calendar SDK(google/apiclient)と実際にやり取りする部分
 * (OAuth認可フロー・カレンダー操作・トークンリフレッシュ)を、HTTPトランスポート層でモックして検証する。
 *
 * `Google\Client::setHttpClient()` にGuzzleのMockHandlerを差し込むことで、SDK内部の実装詳細
 * (Resource::call/REST::execute等)には立ち入らず、Google Calendar APIへの実通信を一切発生させない。
 */
#[Group('external-api')]
class GoogleCalendarServiceSdkTest extends TestCase
{
    use GoogleCalendarTestHelpers, RefreshDatabase;

    public function test_build_auth_url_includes_required_oauth_params(): void
    {
        // 認可URL生成はローカルでの文字列組み立てのみで、HTTP通信は発生しない
        $service = new GoogleCalendarService;

        $url = $service->buildAuthUrl('state-token-abc', 'https://example.test/callback');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('state=state-token-abc', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('https://example.test/callback'), $url);
    }

    public function test_exchange_code_returns_token_on_success(): void
    {
        $client = $this->makeMockedGoogleClient([
            $this->tokenResponse('exchanged-access-token', 'exchanged-refresh-token', 3600),
        ]);
        $service = new GoogleCalendarService($client);

        $token = $service->exchangeCode('auth-code', 'https://example.test/callback');

        $this->assertSame('exchanged-access-token', $token['access_token']);
        $this->assertSame('exchanged-refresh-token', $token['refresh_token']);
        $this->assertSame(3600, $token['expires_in']);
    }

    public function test_exchange_code_throws_when_google_returns_error(): void
    {
        $client = $this->makeMockedGoogleClient([
            $this->tokenErrorResponse('invalid_grant'),
        ]);
        $service = new GoogleCalendarService($client);

        $this->expectException(\RuntimeException::class);

        $service->exchangeCode('bad-code', 'https://example.test/callback');
    }

    public function test_busy_time_keys_returns_empty_when_coach_not_connected(): void
    {
        $coach = User::factory()->coach()->create();
        $service = new GoogleCalendarService;

        $keys = $service->busyTimeKeysForCoach($coach, Carbon::parse('2026-10-05'));

        $this->assertSame([], $keys);
    }

    public function test_busy_time_keys_returns_busy_slots_from_freebusy_response(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create([
            'calendar_id' => 'primary',
            'token_expires_at' => now()->addHour(), // 未失効 → リフレッシュ無しで1回のAPI呼び出しのみ
        ]);

        $client = $this->makeMockedGoogleClient([
            $this->jsonResponse(200, [
                'kind' => 'calendar#freeBusy',
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            ['start' => '2026-10-05T10:15:00+09:00', 'end' => '2026-10-05T10:45:00+09:00'],
                        ],
                    ],
                ],
            ]),
        ]);
        $service = new GoogleCalendarService($client);

        $keys = $service->busyTimeKeysForCoach($coach, Carbon::parse('2026-10-05'));

        $this->assertSame(['10:00'], $keys);
    }

    public function test_busy_time_keys_swallows_api_failure_and_returns_empty(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $client = $this->makeMockedGoogleClient([
            $this->jsonResponse(500, ['error' => ['message' => 'internal error']]),
        ]);
        $service = new GoogleCalendarService($client);

        $keys = $service->busyTimeKeysForCoach($coach, Carbon::parse('2026-10-05'));

        $this->assertSame([], $keys, '通信失敗は握りつぶされ、空き枠判定の根幹を止めないはず');
    }

    public function test_busy_time_keys_refreshes_expired_token_then_succeeds(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->forUser($coach)->create([
            'access_token' => 'stale-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->subHour(), // 失効済
            'calendar_id' => 'primary',
        ]);

        $client = $this->makeMockedGoogleClient([
            $this->tokenResponse('refreshed-access-token', null, 3600), // 1. リフレッシュ成功
            $this->jsonResponse(200, [ // 2. 新トークンでfreebusy取得成功
                'calendars' => ['primary' => ['busy' => []]],
            ]),
        ]);
        $service = new GoogleCalendarService($client);

        $keys = $service->busyTimeKeysForCoach($coach, Carbon::parse('2026-10-05'));

        $this->assertSame([], $keys);
        $credential->refresh();
        $this->assertSame('refreshed-access-token', $credential->access_token);
        // リフレッシュ応答にrefresh_tokenが含まれない場合は既存のrefresh_tokenを保持する
        $this->assertSame('valid-refresh-token', $credential->refresh_token);
        $this->assertTrue($credential->token_expires_at->isFuture());
    }

    public function test_busy_time_keys_falls_back_to_empty_when_refresh_fails(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->forUser($coach)->create([
            'access_token' => 'stale-access-token',
            'refresh_token' => 'revoked-refresh-token',
            'token_expires_at' => now()->subHour(), // 失効済
            'calendar_id' => 'primary',
        ]);

        // リフレッシュ失敗(何度試みても失効したrefresh_tokenでは成功しない想定)を繰り返し返す
        $client = $this->makeMockedGoogleClient([
            $this->tokenErrorResponse('invalid_grant'),
            $this->tokenErrorResponse('invalid_grant'),
            $this->tokenErrorResponse('invalid_grant'),
        ]);
        $service = new GoogleCalendarService($client);

        $keys = $service->busyTimeKeysForCoach($coach, Carbon::parse('2026-10-05'));

        $this->assertSame([], $keys, 'リフレッシュ失敗時も例外を投げず空き枠判定の根幹を止めないはず');
        $credential->refresh();
        $this->assertSame('stale-access-token', $credential->access_token, 'リフレッシュ失敗時は古いトークンのまま上書きしないはず');
    }

    public function test_create_event_returns_null_when_coach_not_connected(): void
    {
        $coach = User::factory()->coach()->create();
        $meeting = $this->makeMeeting($coach);
        $service = new GoogleCalendarService;

        $this->assertNull($service->createEvent($meeting));
    }

    public function test_create_event_returns_event_id_on_success(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);
        $meeting = $this->makeMeeting($coach);

        $client = $this->makeMockedGoogleClient([
            $this->jsonResponse(200, ['kind' => 'calendar#event', 'id' => 'created-event-id-123']),
        ]);
        $service = new GoogleCalendarService($client);

        $this->assertSame('created-event-id-123', $service->createEvent($meeting));
    }

    public function test_create_event_returns_null_when_api_fails(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->forUser($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);
        $meeting = $this->makeMeeting($coach);

        $client = $this->makeMockedGoogleClient([
            $this->jsonResponse(500, ['error' => ['message' => 'internal error']]),
        ]);
        $service = new GoogleCalendarService($client);

        $this->assertNull($service->createEvent($meeting), '予約処理自体は止めないはず(nullで握りつぶす)');
    }

    public function test_delete_event_succeeds_without_throwing(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->forUser($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $client = $this->makeMockedGoogleClient([
            new Response(204),
        ]);
        $service = new GoogleCalendarService($client);

        $service->deleteEvent($credential, 'event-to-delete');

        $this->addToAssertionCount(1); // 例外を投げずに完了すればOK
    }

    public function test_delete_event_on_already_deleted_event_does_not_throw(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->forUser($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        // 既に削除済のイベントに対しては Google は 410 Gone / 404 Not Found を返しうる
        $client = $this->makeMockedGoogleClient([
            $this->jsonResponse(410, ['error' => ['message' => 'Resource has been deleted', 'code' => 410]]),
        ]);
        $service = new GoogleCalendarService($client);

        $service->deleteEvent($credential, 'already-deleted-event-id');

        $this->addToAssertionCount(1); // 例外を投げずに完了すればOK(削除済みは成功扱いでよい)
    }

    private function makeMeeting(User $coach): Meeting
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        return Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'enrollment_id' => $enrollment->id,
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
    }
}
