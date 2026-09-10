<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard\Replies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_reply(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch(
            route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]),
            ['body' => '更新後の回答本文']
        );

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id, 'body' => '更新後の回答本文']);
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($other)->patch(
            route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]),
            ['body' => '更新後の回答本文']
        );

        $response->assertForbidden();
    }
}
