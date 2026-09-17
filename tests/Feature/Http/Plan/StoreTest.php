<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '3ヶ月プラン',
            'description' => '説明文',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'sort_order' => 10,
        ], $overrides);
    }

    public function test_admin_can_create_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '3ヶ月プラン',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_name_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_duration_days_out_of_range_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['duration_days' => 0]))
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['duration_days' => 3651]))
            ->assertSessionHasErrors('duration_days');
    }

    public function test_duration_days_boundary_values_pass(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['duration_days' => 1]))
            ->assertSessionDoesntHaveErrors('duration_days');

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['duration_days' => 3650]))
            ->assertSessionDoesntHaveErrors('duration_days');
    }

    public function test_default_meeting_quota_out_of_range_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['default_meeting_quota' => -1]))
            ->assertSessionHasErrors('default_meeting_quota');

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['default_meeting_quota' => 1001]))
            ->assertSessionHasErrors('default_meeting_quota');
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.plans.store'), $this->payload());

        $response->assertForbidden();
    }
}
