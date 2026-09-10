<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_any_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id]);

        $response = $this->actingAs($admin)->delete(
            route('admin.qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply])
        );

        $response->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }
}
