<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($coach)->get(route('admin.plans.show', $plan))->assertForbidden();
    }
}
