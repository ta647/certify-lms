<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'chat_message_received', 'title' => 'タイトル', 'message' => '本文', 'url' => '/chat-rooms/x'],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_returns_own_notifications_with_unread_count(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);
        $this->createNotification($user, read: true);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJson(['unread_count' => 1]);
        $response->assertJsonFragment([
            'id' => $notification->id,
            'title' => 'タイトル',
            'message' => '本文',
            'url' => '/chat-rooms/x',
            'is_read' => false,
        ]);
    }

    public function test_does_not_return_other_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $this->createNotification($other);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJson(['unread_count' => 0, 'notifications' => []]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
