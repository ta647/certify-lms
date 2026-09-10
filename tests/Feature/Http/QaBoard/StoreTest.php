<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /qa-board` の入力検証と作成結果を確認する。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_thread_for_published_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '2分探索木の比較回数について',
            'body' => '平均比較回数のオーダーがイメージできません。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => '2分探索木の比較回数について',
            'status' => 'unresolved',
        ]);
    }

    public function test_certification_must_be_published(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draft->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertSessionHasErrors('certification_id');
    }

    public function test_title_and_body_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '',
            'body' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertForbidden();
    }
}
