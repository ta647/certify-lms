<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /qa-board/{thread}` の閲覧可否を検証する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_student_can_view_thread_of_published_certification(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $viewer = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $author->id]);

        $this->actingAs($viewer)->get(route('qa-board.show', $thread))->assertOk();
    }

    public function test_coach_forbidden_for_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_coach_can_view_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertOk();
    }

    public function test_student_forbidden_for_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id]);

        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertForbidden();
    }
}
