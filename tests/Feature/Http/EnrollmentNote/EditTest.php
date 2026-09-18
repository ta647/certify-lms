<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_view_edit_form(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['user_id' => $coach->id]);

        $this->actingAs($coach)->get(route('enrollment-notes.edit', $note))->assertOk();
    }

    public function test_admin_can_view_edit_form_for_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();

        $this->actingAs($admin)->get(route('enrollment-notes.edit', $note))->assertOk();
    }

    public function test_other_coach_forbidden(): void
    {
        $author = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['user_id' => $author->id]);

        $this->actingAs($otherCoach)->get(route('enrollment-notes.edit', $note))->assertForbidden();
    }
}
