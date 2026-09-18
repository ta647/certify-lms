<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_note(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['user_id' => $coach->id]);

        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ']);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id, 'body' => '更新後のメモ']);
    }

    public function test_admin_can_update_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();

        $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), ['body' => '運営による修正'])
            ->assertRedirect(route('enrollments.show', $enrollment));
    }

    public function test_other_coach_forbidden(): void
    {
        $author = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['user_id' => $author->id]);

        $this->actingAs($otherCoach)->patch(route('enrollment-notes.update', $note), ['body' => '書き換え'])
            ->assertForbidden();
    }
}
