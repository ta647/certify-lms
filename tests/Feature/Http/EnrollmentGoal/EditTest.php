<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_edit_form(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $this->actingAs($student)->get(route('enrollment-goals.edit', $goal))->assertOk();
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $this->actingAs($other)->get(route('enrollment-goals.edit', $goal))->assertForbidden();
    }
}
