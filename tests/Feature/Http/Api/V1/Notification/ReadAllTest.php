<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReadAllTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'chat_message_received', 'title' => 'タイトル', 'message' => '本文', 'url' => '/chat-rooms/x'],
            'read_at' => null,
        ]);
    }

    public function test_marks_all_own_unread_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $first = $this->createNotification($user);
        $second = $this->createNotification($user);

        $response = $this->actingAs($user)->postJson('/api/v1/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['unread_count' => 0]);
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_does_not_affect_other_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $othersNotification = $this->createNotification($other);

        $this->actingAs($user)->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertNull($othersNotification->fresh()->read_at);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }
}
