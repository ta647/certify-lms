<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Services\GeminiClient;
use App\UseCases\AiChat\SendMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private function mockGemini(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(GeminiClient::class);
        $this->app->instance(GeminiClient::class, $mock);

        return $mock;
    }

    public function test_saves_user_and_assistant_messages_on_success(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $mock = $this->mockGemini();
        $mock->shouldReceive('generateReply')->once()->andReturn([
            'content' => 'AIの回答です',
            'model' => 'gemini-2.5-flash',
            'output_tokens' => 100,
            'response_time_ms' => 500,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn(null);

        $result = app(SendMessageAction::class)($conversation, $student, '質問です');

        $this->assertSame(AiChatMessageRole::User, $result['user_message']->role);
        $this->assertSame('質問です', $result['user_message']->content);
        $this->assertSame(AiChatMessageStatus::Completed, $result['assistant_message']->status);
        $this->assertSame('AIの回答です', $result['assistant_message']->content);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_throws_daily_limit_exception_when_limit_reached(): void
    {
        config(['ai-chat.daily_message_limit' => 2]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        AiChatMessage::factory()->count(2)->forConversation($conversation)->create([
            'role' => AiChatMessageRole::User->value,
        ]);

        $mock = $this->mockGemini();
        $mock->shouldNotReceive('generateReply');

        try {
            app(SendMessageAction::class)($conversation, $student, '質問です');
            $this->fail('例外が投げられるはず');
        } catch (AiChatDailyLimitExceededException) {
            // 期待通り
        }

        $this->assertDatabaseCount('ai_chat_messages', 2);
    }

    public function test_saves_error_status_and_keeps_user_message_when_gemini_fails(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create();

        $this->mockGemini()->shouldReceive('generateReply')
            ->once()
            ->andThrow(new GeminiApiException('upstream error', 502));

        try {
            app(SendMessageAction::class)($conversation, $student, '質問です');
            $this->fail('例外が投げられるはず');
        } catch (GeminiApiException $e) {
            $this->assertSame(502, $e->upstreamStatus());
        }

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '質問です',
            'status' => AiChatMessageStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);
    }

    public function test_auto_generates_title_only_on_first_successful_completion(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create(['title' => '新規相談']);

        $mock = $this->mockGemini();
        $mock->shouldReceive('generateReply')->twice()->andReturn([
            'content' => 'AIの回答',
            'model' => 'gemini-2.5-flash',
            'output_tokens' => null,
            'response_time_ms' => 300,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn('二分探索木の比較回数');

        $first = app(SendMessageAction::class)($conversation, $student, '1回目の質問');
        $this->assertTrue($first['title_updated']);
        $this->assertSame('二分探索木の比較回数', $conversation->fresh()->title);

        $second = app(SendMessageAction::class)($conversation, $student, '2回目の質問');
        $this->assertFalse($second['title_updated']);
    }

    public function test_does_not_auto_generate_title_when_disabled(): void
    {
        config(['ai-chat.auto_title' => false]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->forUser($student)->create(['title' => '新規相談']);

        $mock = $this->mockGemini();
        $mock->shouldReceive('generateReply')->once()->andReturn([
            'content' => 'AIの回答',
            'model' => 'gemini-2.5-flash',
            'output_tokens' => null,
            'response_time_ms' => 300,
        ]);
        $mock->shouldNotReceive('generateTitle');

        $result = app(SendMessageAction::class)($conversation, $student, '質問');

        $this->assertFalse($result['title_updated']);
        $this->assertSame('新規相談', $conversation->fresh()->title);
    }

    public function test_certification_context_prefers_conversation_enrollment_over_default(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $cert = Certification::factory()->published()->create(['name' => '会話紐づき資格']);
        $part = Part::factory()->for($cert)->create();
        $chapter = Chapter::factory()->for($part)->create();
        $section = Section::factory()->for($chapter)->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create([
            'certification_id' => $cert->id,
            'status' => 'learning',
        ]);

        $defaultCert = Certification::factory()->published()->create(['name' => 'デフォルト資格']);
        $defaultEnrollment = Enrollment::factory()->for($student, 'user')->create([
            'certification_id' => $defaultCert->id,
            'status' => 'learning',
        ]);
        $student->update(['default_enrollment_id' => $defaultEnrollment->id]);

        $conversation = AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $mock = $this->mockGemini();
        $mock->shouldReceive('generateReply')
            ->once()
            ->with(Mockery::on(fn ($systemContext) => str_contains($systemContext, '会話紐づき資格')
                && ! str_contains($systemContext, 'デフォルト資格')), Mockery::any(), Mockery::any())
            ->andReturn(['content' => 'ok', 'model' => 'm', 'output_tokens' => null, 'response_time_ms' => 1]);
        $mock->shouldReceive('generateTitle')->once()->andReturn(null);

        app(SendMessageAction::class)($conversation, $student->fresh(), '質問');
    }
}
