<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard\Replies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_edit_form(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $reply]))
            ->assertOk();
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->get(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $reply]))
            ->assertForbidden();
    }

    public function test_mismatched_thread_returns_404(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $otherThread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('qa-board.replies.edit', ['thread' => $otherThread, 'reply' => $reply]))
            ->assertNotFound();
    }
}
