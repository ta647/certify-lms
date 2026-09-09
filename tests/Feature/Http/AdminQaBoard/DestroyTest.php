<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_any_thread_even_with_replies(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);
        QaReply::factory()->create(['qa_thread_id' => $thread->id]);

        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_non_admin_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $student->id]);

        $this->actingAs($student)->delete(route('admin.qa-board.destroy', $thread))->assertForbidden();
    }
}
