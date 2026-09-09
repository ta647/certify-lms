<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard\Replies;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => '同じ疑問を持っていました。参考になりました。',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $response = $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答本文',
        ]);

        $response->assertForbidden();
    }

    public function test_body_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
    }
}
