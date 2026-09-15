<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_basic_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), [
            'name' => '更新後パック',
            'description' => '更新後の説明',
            'meeting_count' => 7,
            'price' => 15000,
            'stripe_price_id' => null,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '更新後パック',
            'meeting_count' => 7,
            'price' => 15000,
        ]);
    }

    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), [
            'name' => '更新後パック',
            'meeting_count' => 5,
            'price' => 12000,
        ]);

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($coach)->patch(route('admin.meeting-packs.update', $plan), [
            'name' => '更新後パック',
            'meeting_count' => 5,
            'price' => 12000,
        ]);

        $response->assertForbidden();
    }
}
