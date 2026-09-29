<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiApiException;
use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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
