<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard\Replies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_reply(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete(
            route('qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply])
        );

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->delete(route('qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply]))
            ->assertForbidden();
    }

    public function test_mismatched_thread_returns_404(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $otherThread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->delete(route('qa-board.replies.destroy', ['thread' => $otherThread, 'reply' => $reply]))
            ->assertNotFound();
    }
}
