<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問5年分を解き終える',
            'description' => '毎日1年分ずつ進める',
            'target_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問5年分を解き終える',
        ]);
    }

    public function test_title_required_and_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), ['title' => str_repeat('a', 101)])
            ->assertSessionHasErrors('title');
    }

    public function test_other_student_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();

        $this->actingAs($other)->post(route('enrollments.goals.store', $enrollment), ['title' => 'タイトル'])
            ->assertForbidden();
    }

    public function test_coach_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();

        $this->actingAs($coach)->post(route('enrollments.goals.store', $enrollment), ['title' => 'タイトル'])
            ->assertForbidden();
    }
}
