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
 * `GET /qa-board` の一覧表示範囲を検証する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_threads_of_published_certifications_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        $visibleThread = QaThread::factory()->create(['certification_id' => $published->id, 'user_id' => $student->id]);
        QaThread::factory()->create(['certification_id' => $draft->id, 'user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee($visibleThread->title);
    }

    public function test_coach_sees_threads_of_assigned_certifications_only(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $assigned = Certification::factory()->published()->create();
        $unassigned = Certification::factory()->published()->create();

        CertificationCoachAssignment::factory()->create([
            'certification_id' => $assigned->id,
            'user_id' => $coach->id,
        ]);

        $visibleThread = QaThread::factory()->create(['certification_id' => $assigned->id]);
        $hiddenThread = QaThread::factory()->create(['certification_id' => $unassigned->id]);

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee($visibleThread->title);
        $response->assertDontSee($hiddenThread->title);
    }

    public function test_admin_forbidden_on_public_route(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->get(route('qa-board.index'))->assertForbidden();
    }
}
