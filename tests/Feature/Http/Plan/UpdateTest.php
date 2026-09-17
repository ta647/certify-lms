<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_basic_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'description' => '更新後の説明',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
        ]);
    }

    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
        ]);

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($coach)->put(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
        ]);

        $response->assertForbidden();
    }
}
