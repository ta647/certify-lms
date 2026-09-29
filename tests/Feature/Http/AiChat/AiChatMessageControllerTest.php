<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageRole;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AiChatMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockGemini(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(GeminiClient::class);
        $this->app->instance(GeminiClient::class, $mock);

        return $mock;
    }

    public function test_store_returns_messages_and_conversation_on_success(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $mock = $this->mockGemini();
        $mock->shouldReceive('generateReply')->once()->andReturn([
            'content' => 'AIの回答です', 'model' => 'gemini-2.5-flash', 'output_tokens' => 10, 'response_time_ms' => 200,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn('自動生成タイトル');

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        );

        $response->assertOk();
        $response->assertJsonPath('user_message.content', '質問です');
        $response->assertJsonPath('assistant_message.content', 'AIの回答です');
        $response->assertJsonPath('conversation.title', '自動生成タイトル');
    }

    public function test_store_returns_429_when_daily_limit_exceeded(): void
    {
        config(['ai-chat.daily_message_limit' => 1]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();
        AiChatMessage::factory()->forConversation($conversation)->create(['role' => AiChatMessageRole::User->value]);

        $this->mockGemini()->shouldNotReceive('generateReply');

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        );

        $response->assertStatus(429);
    }

    public function test_store_returns_502_with_upstream_status_on_gemini_failure(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $this->mockGemini()->shouldReceive('generateReply')->once()->andThrow(
            new GeminiApiException('failed', 503),
        );

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        );

        $response->assertStatus(502);
        $response->assertJsonPath('upstream_status', 503);
    }

    public function test_store_returns_422_for_empty_content(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => ''],
        );

        $response->assertStatus(422);
    }

    public function test_store_returns_422_for_too_long_content(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => str_repeat('a', 2001)],
        );

        $response->assertStatus(422);
    }

    public function test_store_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($owner)->create();

        $this->actingAs($other)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        )->assertForbidden();
    }
}
