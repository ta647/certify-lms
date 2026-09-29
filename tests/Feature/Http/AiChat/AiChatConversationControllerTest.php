<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AiChatConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_to_latest_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        AiChatConversation::factory()->forUser($student)->create(['last_message_at' => now()->subDay()]);
        $latest = AiChatConversation::factory()->forUser($student)->create(['last_message_at' => now()]);

        $response = $this->actingAs($student)->get(route('ai-chat.index'));

        $response->assertRedirect(route('ai-chat.conversations.show', $latest));
    }

    public function test_index_shows_empty_state_when_no_conversations(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('ai-chat.index'));

        $response->assertOk();
        $response->assertViewIs('ai-chat.empty-state');
    }

    public function test_coach_cannot_access_index(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_graduated_student_cannot_access_index(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_store_json_creates_new_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['conversation' => ['id', 'title']]);
    }

    public function test_store_json_reuses_existing_conversation_for_same_section(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $existing = AiChatConversation::factory()->forUser($student)->create(['last_message_at' => now()]);

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('conversation.id', $existing->id);
    }

    public function test_store_html_without_message_creates_conversation_and_redirects(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
        ]);

        $conversation = $student->aiChatConversations()->first();
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
    }

    public function test_store_html_with_message_sends_synchronously_before_redirect(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('generateReply')->once()->andReturn([
            'content' => 'AIの回答', 'model' => 'm', 'output_tokens' => null, 'response_time_ms' => 1,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn(null);
        $this->app->instance(GeminiClient::class, $mock);

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
            'message' => '最初の質問です',
        ]);

        $conversation = $student->aiChatConversations()->first();
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'content' => '最初の質問です',
        ]);
    }

    public function test_store_html_with_message_gemini_failure_still_creates_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('generateReply')->once()->andThrow(
            new \App\Exceptions\AiChat\GeminiApiException('failed', 502),
        );
        $this->app->instance(GeminiClient::class, $mock);

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
            'message' => '最初の質問です',
        ]);

        $conversation = $student->aiChatConversations()->first();
        $this->assertNotNull($conversation);
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $response->assertSessionHas('error');
    }

    public function test_show_renders_html_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $response = $this->actingAs($student)->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertViewIs('ai-chat.show');
    }

    public function test_show_returns_json_messages_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();
        AiChatMessage::factory()->forConversation($conversation)->create(['content' => 'こんにちは']);

        $response = $this->actingAs($student)->getJson(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertJsonFragment(['content' => 'こんにちは']);
    }

    public function test_show_forbidden_for_other_students_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($owner)->create();

        $this->actingAs($other)->get(route('ai-chat.conversations.show', $conversation))->assertForbidden();
    }

    public function test_update_renames_title(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create(['title' => '新規相談']);

        $response = $this->actingAs($student)->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => '二分探索木について',
        ]);

        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertSame('二分探索木について', $conversation->fresh()->title);
    }

    public function test_update_forbidden_for_other_students_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($owner)->create();

        $this->actingAs($other)
            ->patch(route('ai-chat.conversations.update', $conversation), ['title' => '乗っ取り'])
            ->assertForbidden();
    }

    public function test_destroy_removes_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $response = $this->actingAs($student)->delete(route('ai-chat.conversations.destroy', $conversation));

        $response->assertRedirect(route('ai-chat.index'));
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_destroy_forbidden_for_other_students_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($owner)->create();

        $this->actingAs($other)->delete(route('ai-chat.conversations.destroy', $conversation))->assertForbidden();
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conversation->id]);
    }
}
