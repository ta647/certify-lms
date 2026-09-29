<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChat\StoreConversationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PM回答(Q2)の会話再利用ルールを検証する:
 * ウィジェット経由(source=widget)は同じ受講生×同じSectionの会話を再利用、フル画面は常に新規作成。
 */
class StoreConversationActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSection(): Section
    {
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->create();
        $chapter = Chapter::factory()->for($part)->create();

        return Section::factory()->for($chapter)->create();
    }

    public function test_widget_creates_new_conversation_when_none_exists(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertTrue($conversation->wasRecentlyCreated);
        $this->assertSame($student->id, $conversation->user_id);
        $this->assertSame($section->id, $conversation->section_id);
    }

    public function test_widget_reuses_existing_conversation_for_same_user_and_section(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        $existing = AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'last_message_at' => now(),
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertFalse($conversation->wasRecentlyCreated);
        $this->assertSame($existing->id, $conversation->id);
    }

    public function test_widget_does_not_reuse_other_users_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        AiChatConversation::factory()->forUser($other)->create([
            'section_id' => $section->id,
            'last_message_at' => now(),
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertTrue($conversation->wasRecentlyCreated);
    }

    public function test_widget_reuses_most_recently_active_conversation_when_multiple_exist(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'last_message_at' => now()->subDays(3),
        ]);
        $recent = AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'last_message_at' => now()->subHour(),
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertSame($recent->id, $conversation->id);
    }

    public function test_widget_general_conversation_reuse_ignores_section_scoped_ones(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'last_message_at' => now(),
        ]);
        $general = AiChatConversation::factory()->forUser($student)->create([
            'section_id' => null,
            'last_message_at' => now()->subMinutes(5),
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'widget', null);

        $this->assertSame($general->id, $conversation->id);
    }

    public function test_full_screen_always_creates_new_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        AiChatConversation::factory()->forUser($student)->create([
            'section_id' => $section->id,
            'last_message_at' => now(),
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'full-screen', null);

        $this->assertTrue($conversation->wasRecentlyCreated);
        $this->assertSame(2, AiChatConversation::where('user_id', $student->id)->count());
    }

    public function test_resolves_enrollment_id_when_student_is_learning_the_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();
        $certificationId = $section->chapter->part->certification_id;
        $enrollment = Enrollment::factory()->for($student, 'user')->create([
            'certification_id' => $certificationId,
            'status' => 'learning',
        ]);

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertSame($enrollment->id, $conversation->enrollment_id);
    }

    public function test_enrollment_id_is_null_when_student_is_not_enrolled(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->makeSection();

        $conversation = app(StoreConversationAction::class)($student, 'widget', $section->id);

        $this->assertNull($conversation->enrollment_id);
    }
}
