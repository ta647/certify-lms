<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `DELETE /qa-board/{thread}` の削除条件を検証する。
 *
 * 暫定仕様(計画書「保留事項1」): 投稿者本人は回答が 1 件も付いていないスレッドのみ削除できる。
 * PM 回答後に条件が変わる場合は本テストも合わせて更新する。
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_thread_without_replies(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_owner_cannot_delete_thread_with_replies(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);
        QaReply::factory()->create(['qa_thread_id' => $thread->id]);

        $response = $this->actingAs($owner)->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect();
        $response->assertSessionHas('error', '回答が付いているスレッドは削除できません。');
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_non_owner_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create(['certification_id' => $certification->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->delete(route('qa-board.destroy', $thread))->assertForbidden();
    }
}
