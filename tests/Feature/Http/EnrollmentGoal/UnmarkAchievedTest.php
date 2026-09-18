<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmarkAchievedTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_unmark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_cannot_unmark_when_not_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertForbidden();
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();

        $this->actingAs($other)->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertForbidden();
    }
}
