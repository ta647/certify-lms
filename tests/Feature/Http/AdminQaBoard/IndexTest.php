<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_threads_of_unpublished_certifications_too(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->create(['certification_id' => $draft->id]);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();
        $response->assertSee($thread->title);
    }

    public function test_non_admin_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('admin.qa-board.index'))->assertForbidden();
    }
}
