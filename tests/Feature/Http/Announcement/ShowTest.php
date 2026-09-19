<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $announcement));

        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
    }

    public function test_non_admin_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $announcement = Announcement::factory()->create();

        $this->actingAs($coach)->get(route('admin.announcements.show', $announcement))->assertForbidden();
    }
}
