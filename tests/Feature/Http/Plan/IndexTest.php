<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['name' => '6ヶ月プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSee($plan->name);
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Plan::factory()->create(['name' => '12ヶ月プラン']);
        $other = Plan::factory()->create(['name' => '1ヶ月プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['keyword' => '12ヶ月']));

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($other->name);
    }

    public function test_status_filters_results(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = Plan::factory()->draft()->create(['name' => '下書きプラン']);
        $published = Plan::factory()->published()->create(['name' => '公開プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee($draft->name);
        $response->assertDontSee($published->name);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_student_forbidden(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('admin.plans.index'))->assertForbidden();
    }
}
