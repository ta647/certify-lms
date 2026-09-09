<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resolve_unresolved_thread(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $owner->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        $response = $this->actingAs($owner)->post(route('qa-board.resolve', $thread));

        $response->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);
    }

    public function test_cannot_resolve_already_resolved_thread(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $owner->id,
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('qa-board.resolve', $thread))->assertForbidden();
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->post(route('qa-board.resolve', $thread))->assertForbidden();
    }
}
