<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Calendar とのやり取りを一手に引き受ける Service。
 *
 * このクラス以外から Google SDK(`Google\Client` / `Google\Service\Calendar`)へ直接触れないようにし、
 * テスト時はこのクラス自体をモックする境界とする。
 *
 * 通信失敗はすべてこのクラス内で握りつぶし(ログに残すのみ)、呼出側(空き枠表示 / 予約 / キャンセル)の
 * 根幹処理を止めない。トークンの自動リフレッシュもここで完結させる。
 */
class GoogleCalendarService
{
    public function __construct(private readonly ?Client $client = null) {}

    /**
     * OAuth 認可 URL を生成する。`state` は呼出側(Controller)がセッションに保存し、
     * コールバック時に一致確認することでなりすまし・CSRF を防ぐ。
     */
    public function buildAuthUrl(string $state, string $redirectUri): string
    {
        $client = $this->freshClient();
        $client->setRedirectUri($redirectUri);
        $client->setState($state);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([Calendar::CALENDAR]);

        return $client->createAuthUrl();
    }

    /**
     * 認可コードをアクセストークンに交換する。
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}
     *
     * @throws \RuntimeException 交換に失敗した場合
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $client = $this->freshClient();
        $client->setRedirectUri($redirectUri);
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google認可コードの交換に失敗しました: '.$token['error']);
        }

        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_in' => (int) ($token['expires_in'] ?? 3600),
        ];
    }

    /**
     * 指定コーチの指定日について、Googleカレンダー上busyな60分スロットの開始時刻("H:i")集合を返す。
     * 未連携コーチは常に空配列。通信失敗時も空配列(空き枠判定の根幹を止めない)。
     *
     * @return array<int, string>
     */
    public function busyTimeKeysForCoach(User $coach, Carbon $date): array
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return [];
        }

        try {
            $client = $this->authorizedClient($credential);
            $service = new Calendar($client);

            $timeMin = $date->copy()->startOfDay();
            $timeMax = $date->copy()->endOfDay();

            $item = new FreeBusyRequestItem;
            $item->setId($credential->calendar_id);

            $request = new FreeBusyRequest;
            $request->setTimeMin($timeMin->toRfc3339String());
            $request->setTimeMax($timeMax->toRfc3339String());
            $request->setItems([$item]);

            $response = $service->freebusy->query($request);
            $calendars = $response->getCalendars();
            $busyPeriods = $calendars[$credential->calendar_id]->getBusy() ?? [];

            $periods = array_map(
                fn ($period) => ['start' => (string) $period->getStart(), 'end' => (string) $period->getEnd()],
                $busyPeriods,
            );

            return self::busyKeysFromPeriods($periods, $timeMin, $timeMax);
        } catch (Throwable $e) {
            Log::warning('Google Calendar busy-time取得に失敗しました', [
                'coach_id' => $coach->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 面談成立時にGoogleカレンダーへ予定を作成し、作成されたイベントIDを返す。
     * コーチが未連携、または通信失敗時はnullを返す(予約処理は止めない)。
     */
    public function createEvent(Meeting $meeting): ?string
    {
        $meeting->loadMissing(['coach.googleCredential', 'student']);
        $credential = $meeting->coach->googleCredential;
        if ($credential === null) {
            return null;
        }

        try {
            $client = $this->authorizedClient($credential);
            $service = new Calendar($client);

            $start = $meeting->scheduled_at->copy();
            $end = $start->copy()->addMinutes(60);

            $event = new Event;
            $event->setSummary($meeting->student->name.'さんとの面談');
            $event->setDescription($meeting->topic);
            if ($meeting->meeting_url_snapshot !== null) {
                $event->setLocation($meeting->meeting_url_snapshot);
            }

            $eventStart = new EventDateTime;
            $eventStart->setDateTime($start->toRfc3339String());
            $eventStart->setTimeZone(config('app.timezone'));
            $event->setStart($eventStart);

            $eventEnd = new EventDateTime;
            $eventEnd->setDateTime($end->toRfc3339String());
            $eventEnd->setTimeZone(config('app.timezone'));
            $event->setEnd($eventEnd);

            $created = $service->events->insert($credential->calendar_id, $event);

            return $created->getId();
        } catch (Throwable $e) {
            Log::warning('Google Calendarへの予定作成に失敗しました', [
                'meeting_id' => $meeting->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 面談キャンセル時にGoogleカレンダー上の予定を削除する。失敗しても例外を投げない。
     */
    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void
    {
        try {
            $client = $this->authorizedClient($credential);
            $service = new Calendar($client);
            $service->events->delete($credential->calendar_id, $eventId);
        } catch (Throwable $e) {
            Log::warning('Google Calendarの予定削除に失敗しました', [
                'credential_id' => $credential->id,
                'event_id' => $eventId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 認証済みClientを組み立てる。アクセストークン期限切れ時はrefresh_tokenで更新し、
     * 更新後のトークンを対象Credential行に書き戻す(「連携は一度設定すれば継続して使える」要件)。
     */
    private function authorizedClient(GoogleCalendarCredential $credential): Client
    {
        $client = $this->freshClient();
        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
            'expires_in' => $credential->token_expires_at !== null
                ? max(0, now()->diffInSeconds($credential->token_expires_at, false))
                : 0,
        ]);

        if ($client->isAccessTokenExpired() && $credential->refresh_token !== null) {
            $refreshed = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);

            if (! isset($refreshed['error'])) {
                $credential->update([
                    'access_token' => $refreshed['access_token'],
                    'refresh_token' => $refreshed['refresh_token'] ?? $credential->refresh_token,
                    'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
                ]);
            }
        }

        return $client;
    }

    /**
     * busy期間の配列(RFC3339文字列のstart/end)を、[timeMin, timeMax) 内の60分スロット開始時刻("H:i")の
     * 集合に変換する純粋関数。Google APIから独立してテストできるようロジックを切り出している。
     *
     * @param array<int, array{start: string, end: string}> $periods
     *
     * @return array<int, string>
     */
    public static function busyKeysFromPeriods(array $periods, Carbon $timeMin, Carbon $timeMax): array
    {
        $busyKeys = [];

        foreach ($periods as $period) {
            $start = Carbon::parse($period['start']);
            $end = Carbon::parse($period['end']);

            for ($slot = $timeMin->copy(); $slot->lt($timeMax); $slot->addHour()) {
                $slotEnd = $slot->copy()->addHour();
                if ($slot->lt($end) && $slotEnd->gt($start)) {
                    $busyKeys[] = $slot->format('H:i');
                }
            }
        }

        return array_values(array_unique($busyKeys));
    }

    private function freshClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $client = new Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));

        return $client;
    }
}
