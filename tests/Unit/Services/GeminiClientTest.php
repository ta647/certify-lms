<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiApiException;
use App\Services\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class GeminiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.gemini.model' => 'gemini-2.5-flash',
        ]);
    }

    public function test_generate_reply_returns_content_and_metadata_on_success(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'こんにちは、AIです。']]]],
                ],
                'usageMetadata' => ['candidatesTokenCount' => 42],
            ], 200),
        ]);

        $result = app(GeminiClient::class)->generateReply(null, [], '質問です');

        $this->assertSame('こんにちは、AIです。', $result['content']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertSame(42, $result['output_tokens']);
        $this->assertIsInt($result['response_time_ms']);
    }

    public function test_generate_reply_maps_assistant_role_to_model_in_history(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '応答']]]]],
            ], 200),
        ]);

        app(GeminiClient::class)->generateReply(
            'システム文脈',
            [
                ['role' => 'user', 'content' => '過去の質問'],
                ['role' => 'assistant', 'content' => '過去の回答'],
            ],
            '今回の質問',
        );

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['systemInstruction']['parts'][0]['text'] === 'システム文脈'
                && $body['contents'][0]['role'] === 'user'
                && $body['contents'][1]['role'] === 'model'
                && $body['contents'][2]['role'] === 'user'
                && $body['contents'][2]['parts'][0]['text'] === '今回の質問';
        });
    }

    public function test_generate_reply_throws_with_upstream_status_on_failure(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['error' => ['message' => 'rate limited']], 429),
        ]);

        try {
            app(GeminiClient::class)->generateReply(null, [], '質問');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            $this->assertSame(429, $e->upstreamStatus());
        }

        // 429はリトライ対象外: 1回のみ試行して即座に失敗するはず
        Http::assertSentCount(1);
    }

    public function test_generate_reply_does_not_retry_on_other_client_errors(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['error' => ['message' => 'bad request']], 400),
        ]);

        try {
            app(GeminiClient::class)->generateReply(null, [], '質問');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            $this->assertSame(400, $e->upstreamStatus());
        }

        Http::assertSentCount(1);
    }

    public function test_generate_reply_throws_when_response_has_no_candidates(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['candidates' => []], 200),
        ]);

        try {
            app(GeminiClient::class)->generateReply(null, [], '質問');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            $this->assertSame('Gemini APIの応答形式が不正です', $e->getMessage());
        }
    }

    public function test_generate_reply_throws_on_connection_error(): void
    {
        $attempts = 0;
        Http::fake([
            '*generativelanguage.googleapis.com*' => function () use (&$attempts) {
                $attempts++;

                throw new ConnectionException('接続タイムアウト');
            },
        ]);

        try {
            app(GeminiClient::class)->generateReply(null, [], '質問');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            $this->assertNull($e->upstreamStatus());
            $this->assertStringContainsString('Gemini APIへの接続に失敗しました', $e->getMessage());
        }

        // 通信エラーは最大2回リトライ = 計3回試行してから諦めるはず
        $this->assertSame(3, $attempts);
    }

    public function test_generate_reply_retries_on_server_error_then_succeeds(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::sequence()
                ->push(['error' => ['message' => 'internal error']], 500)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'リトライ成功']]]]],
                ], 200),
        ]);

        $result = app(GeminiClient::class)->generateReply(null, [], '質問');

        $this->assertSame('リトライ成功', $result['content']);
        Http::assertSentCount(2);
    }

    public function test_generate_reply_retries_on_connection_error_then_succeeds(): void
    {
        $attempts = 0;
        Http::fake([
            '*generativelanguage.googleapis.com*' => function () use (&$attempts) {
                $attempts++;

                if ($attempts === 1) {
                    throw new ConnectionException('一時的な接続エラー');
                }

                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => '接続復旧後に成功']]]]],
                ], 200);
            },
        ]);

        $result = app(GeminiClient::class)->generateReply(null, [], '質問');

        $this->assertSame('接続復旧後に成功', $result['content']);
        $this->assertSame(2, $attempts);
    }

    public function test_generate_reply_gives_up_after_max_retries_on_persistent_server_error(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['error' => ['message' => 'internal error']], 500),
        ]);

        try {
            app(GeminiClient::class)->generateReply(null, [], '質問');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            // リトライを尽くしても失敗した場合は、通常の失敗時と同じ例外形(status付き)に帰着する
            $this->assertSame(500, $e->upstreamStatus());
        }

        // 最大2回リトライ = 計3回試行してから諦めるはず
        Http::assertSentCount(3);
    }

    public function test_generate_title_returns_null_on_failure_without_throwing(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([], 500),
        ]);

        $title = app(GeminiClient::class)->generateTitle('質問', '回答');

        $this->assertNull($title);
    }

    public function test_generate_title_returns_trimmed_text_on_success(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => "  二分探索木の平均比較回数\n"]]]]],
            ], 200),
        ]);

        $title = app(GeminiClient::class)->generateTitle('質問', '回答');

        $this->assertSame('二分探索木の平均比較回数', $title);
    }
}
