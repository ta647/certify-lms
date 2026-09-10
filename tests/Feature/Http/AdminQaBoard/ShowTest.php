<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_thread_of_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $archived = Certification::factory()->archived()->create();
        $thread = QaThread::factory()->create(['certification_id' => $archived->id]);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $thread));

        $response->assertOk();
        $response->assertViewIs('qa-thread.show');
    }
}
