<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReadTest extends TestCase
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

    public function test_owner_can_mark_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson(['unread_count' => 0]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_other_users_notification_is_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);

        $response = $this->actingAs($other)->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertUnauthorized();
    }
}
