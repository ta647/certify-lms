<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['name' => '5 回パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertSee($plan->name);
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $match = MeetingPack::factory()->create(['name' => '10 回パック']);
        $other = MeetingPack::factory()->create(['name' => '1 回パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['keyword' => '10 回']));

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($other->name);
    }

    public function test_status_filters_results(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = MeetingPack::factory()->draft()->create(['name' => '下書きパック']);
        $published = MeetingPack::factory()->published()->create(['name' => '公開パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee($draft->name);
        $response->assertDontSee($published->name);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }

    public function test_student_forbidden(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }
}
