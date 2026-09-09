<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_title_and_body(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch(route('qa-board.update', $thread), [
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);
    }

    public function test_certification_id_cannot_be_changed(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $original = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $original->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->patch(route('qa-board.update', $thread), [
            'certification_id' => $other->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $this->assertSame($original->id, $thread->fresh()->certification_id);
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($other)->patch(route('qa-board.update', $thread), [
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertForbidden();
    }
}
