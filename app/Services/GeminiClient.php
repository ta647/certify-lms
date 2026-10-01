<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gemini(Google Generative Language API)とのやり取りを一手に引き受けるService。
 *
 * 専用SDKは未導入のため、既存のgoogle/apiclient(Google Calendar連携用)とは別に、
 * 標準のLaravel Httpファサード(guzzlehttp/guzzle基盤)でREST APIを直接呼ぶ。
 * `Http::fake()`でテストできるため、このクラス自体をモックする必要すら無い。
 *
 * generateReply() は通信エラー / 5xx のみ最大2回リトライする(計3回試行)。429(レート制限)は
 * リトライ対象外で即座に失敗させる。リトライを尽くしても失敗した場合は、通常の失敗時と同じ
 * GeminiApiException/エラー表示に帰着させる(新たな失敗モードを増やさない)。
 */
class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const MAX_RETRIES = 2;

    /** Http::retry()の$timesは合計試行回数の指定のため、初回 + リトライ回数で渡す。 */
    private const MAX_ATTEMPTS = self::MAX_RETRIES + 1;

    private const RETRY_DELAY_MS = 100;

    /**
     * 会話履歴 + 最新の質問からAIの応答を同期的に取得する。
     *
     * @param array<int, array{role: string, content: string}> $history 時系列順、role は 'user'|'assistant'
     *
     * @return array{content: string, model: string, output_tokens: ?int, response_time_ms: int}
     *
     * @throws GeminiApiException
     */
    public function generateReply(?string $systemContext, array $history, string $userContent): array
    {
        $model = (string) config('ai-chat.gemini.model');

        $contents = Collection::make($history)
            ->map(fn (array $m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ])
            ->push(['role' => 'user', 'parts' => [['text' => $userContent]]])
            ->values()
            ->all();

        $payload = ['contents' => $contents];
        if ($systemContext !== null && $systemContext !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemContext]]];
        }

        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('ai-chat.gemini.timeout', 30))
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, $this->shouldRetry(...), throw: false)
                ->post($this->endpoint($model), $payload);
        } catch (Throwable $e) {
            throw new GeminiApiException('Gemini APIへの接続に失敗しました: '.$e->getMessage(), null, $e);
        }

        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            throw new GeminiApiException(
                'Gemini APIがエラーを返しました: '.$response->body(),
                $response->status(),
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new GeminiApiException('Gemini APIの応答形式が不正です', $response->status());
        }

        return [
            'content' => $text,
            'model' => $model,
            'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            'response_time_ms' => $responseTimeMs,
        ];
    }

    /**
     * 会話タイトルを1つだけ生成する(タイトル自動生成専用の軽量呼び出し)。
     * 失敗してもメッセージ送信自体を失敗させたくないため、例外は投げずnullを返す。
     */
    public function generateTitle(string $firstUserMessage, string $firstAssistantReply): ?string
    {
        $model = (string) config('ai-chat.gemini.model');

        $prompt = '以下のやり取りに、日本語で10〜20文字程度の簡潔なタイトルを1つだけ、'.
            "装飾や記号・引用符を付けずプレーンテキストで返してください。\n\n".
            "質問: {$firstUserMessage}\n回答: {$firstAssistantReply}";

        try {
            $response = Http::timeout((int) config('ai-chat.gemini.timeout', 30))
                ->post($this->endpoint($model), [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ]);

            if ($response->failed()) {
                return null;
            }

            $title = $response->json('candidates.0.content.parts.0.text');
            if (! is_string($title) || trim($title) === '') {
                return null;
            }

            return Str::limit(trim($title), 100, '');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * リトライ要否判定。通信エラー(ConnectionException)と5xxはtrue、429を含むそれ以外はfalse。
     */
    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return $exception->response->status() >= 500;
        }

        return false;
    }

    private function endpoint(string $model): string
    {
        $apiKey = (string) config('ai-chat.gemini.api_key');

        return self::BASE_URL."/models/{$model}:generateContent?key={$apiKey}";
    }
}
