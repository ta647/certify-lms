<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '3 回パック',
            'description' => '説明文',
            'meeting_count' => 3,
            'price' => 9000,
            'stripe_price_id' => null,
            'sort_order' => 10,
        ], $overrides);
    }

    public function test_admin_can_create_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_packs', [
            'name' => '3 回パック',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_name_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['name' => '']));

        $response->assertSessionHasErrors('name');
    }

    public function test_meeting_count_out_of_range_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 0]))
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 101]))
            ->assertSessionHasErrors('meeting_count');
    }

    public function test_meeting_count_boundary_values_pass(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 1]))
            ->assertSessionDoesntHaveErrors('meeting_count');

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => 100]))
            ->assertSessionDoesntHaveErrors('meeting_count');
    }

    public function test_price_out_of_range_fails(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['price' => -1]))
            ->assertSessionHasErrors('price');

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['price' => 1000001]))
            ->assertSessionHasErrors('price');
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.meeting-packs.store'), $this->payload());

        $response->assertForbidden();
    }
}
