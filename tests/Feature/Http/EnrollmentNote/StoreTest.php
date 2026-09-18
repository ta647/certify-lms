<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_add_note(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::factory()->create(['certification_id' => $certification->id, 'user_id' => $coach->id]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();

        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '最近chatの応答が遅れがち。次回面談で確認したい。',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_admin_can_add_note_to_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), ['body' => '運営観察メモ'])
            ->assertRedirect(route('enrollments.show', $enrollment));
    }

    public function test_unassigned_coach_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();

        $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), ['body' => 'メモ'])
            ->assertForbidden();
    }

    public function test_student_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $this->actingAs($student)->post(route('enrollments.notes.store', $enrollment), ['body' => 'メモ'])
            ->assertForbidden();
    }

    public function test_body_required_and_max_length(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }
}
