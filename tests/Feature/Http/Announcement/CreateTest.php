<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.announcements.create'))->assertOk();
    }

    public function test_non_admin_forbidden(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.announcements.create'))->assertForbidden();
    }
}
