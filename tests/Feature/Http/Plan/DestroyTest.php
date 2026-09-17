<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_with_no_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan))->assertStatus(409);
    }

    public function test_cannot_delete_draft_with_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($coach)->delete(route('admin.plans.destroy', $plan))->assertForbidden();
    }
}
