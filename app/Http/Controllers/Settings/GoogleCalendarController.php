<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GoogleCalendarCredential;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * コーチのGoogleカレンダー連携(OAuth)を扱うController。
 *
 * 連携対象は常に `$request->user()` 自身の1行のみで、route-model-bindingでIDを指定する経路が無いため
 * 専用Policyは設けていない(AvailabilityControllerと同じ構造)。
 */
class GoogleCalendarController extends Controller
{
    private const DEFAULT_REDIRECT_PATH = '/settings/availability';

    public function redirect(Request $request, GoogleCalendarService $service): RedirectResponse
    {
        $state = Str::random(40);
        $redirectPath = $this->sanitizeRedirectPath($request->query('redirect_path'));

        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_redirect_path', $redirectPath);

        $authUrl = $service->buildAuthUrl($state, route('settings.google-calendar.callback'));

        return redirect()->away($authUrl);
    }

    public function callback(Request $request, GoogleCalendarService $service): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $redirectPath = $request->session()->pull('google_oauth_redirect_path', self::DEFAULT_REDIRECT_PATH);

        $state = $request->query('state');
        if ($state === null || $expectedState === null || ! hash_equals((string) $expectedState, (string) $state)) {
            return redirect($redirectPath)->with('error', 'Googleカレンダー連携に失敗しました。もう一度お試しください。');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect($redirectPath)->with('error', 'Googleカレンダー連携に失敗しました。もう一度お試しください。');
        }

        try {
            $token = $service->exchangeCode($code, route('settings.google-calendar.callback'));
        } catch (\Throwable $e) {
            return redirect($redirectPath)->with('error', 'Googleカレンダー連携に失敗しました。もう一度お試しください。');
        }

        $user = $request->user();
        $existing = $user->googleCredential;

        GoogleCalendarCredential::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? $existing?->refresh_token,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'calendar_id' => 'primary',
                'connected_at' => now(),
            ],
        );

        return redirect($redirectPath)->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->googleCredential?->delete();

        return redirect()->route('settings.availability.index')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }

    private function sanitizeRedirectPath(mixed $redirectPath): string
    {
        if (! is_string($redirectPath) || $redirectPath === '' || ! str_starts_with($redirectPath, '/') || str_starts_with($redirectPath, '//')) {
            return self::DEFAULT_REDIRECT_PATH;
        }

        return $redirectPath;
    }
}
