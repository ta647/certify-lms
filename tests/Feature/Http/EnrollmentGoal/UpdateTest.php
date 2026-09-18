<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '更新後の目標',
            'description' => '更新後の詳細',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id, 'title' => '更新後の目標']);
    }

    public function test_update_does_not_change_achieved_at(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();

        $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), ['title' => '更新後の目標']);

        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $this->actingAs($other)->patch(route('enrollment-goals.update', $goal), ['title' => '更新後の目標'])
            ->assertForbidden();
    }
}
