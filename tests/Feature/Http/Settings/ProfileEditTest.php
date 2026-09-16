<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /settings/profile` はロールに関わらず本人が常にアクセスできる(active-learning対象外)。
 */
class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_coach_can_view(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_admin_can_view(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_graduated_student_can_still_view(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)->get(route('settings.profile.edit'))->assertOk();
    }
}
