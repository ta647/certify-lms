<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_name_and_bio(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '更新後の名前',
            'bio' => '更新後の自己紹介',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '更新後の名前',
            'bio' => '更新後の自己紹介',
        ]);
    }

    public function test_graduated_student_can_still_update(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '更新後の名前',
        ])->assertRedirect(route('settings.profile.edit'));
    }

    public function test_name_required_and_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->patch(route('settings.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($student)->patch(route('settings.profile.update'), ['name' => str_repeat('a', 51)])
            ->assertSessionHasErrors('name');
    }

    public function test_bio_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '名前',
            'bio' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('bio');
    }

    public function test_coach_meeting_url_is_saved(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => 'コーチ名',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->assertSame('https://meet.google.com/abc-defg-hij', $coach->fresh()->meeting_url);
    }

    public function test_student_meeting_url_is_ignored(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '学生名',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->assertNull($student->fresh()->meeting_url);
    }
}
