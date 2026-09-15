<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.meeting-packs.create'))->assertOk();
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('admin.meeting-packs.create'))->assertForbidden();
    }
}
