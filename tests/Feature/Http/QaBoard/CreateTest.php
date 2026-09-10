<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /qa-board/create` は投稿者になり得る受講生のみアクセス可能。
 */
class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_create_form(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('qa-board.create'))->assertOk();
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('qa-board.create'))->assertForbidden();
    }
}
