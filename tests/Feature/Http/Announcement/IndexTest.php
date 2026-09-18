<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['title' => '重要なお知らせ']);

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertSee('重要なお知らせ');
    }

    public function test_non_admin_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('admin.announcements.index'))->assertForbidden();
    }
}
