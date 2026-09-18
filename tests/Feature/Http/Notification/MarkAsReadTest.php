<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, string $url = '/chat-rooms/x'): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'chat_message_received', 'title' => 'タイトル', 'message' => '本文', 'url' => $url],
            'read_at' => null,
        ]);
    }

    public function test_owner_can_mark_as_read_and_is_redirected_to_target_url(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, url: '/chat-rooms/abc');

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification->id));

        $response->assertRedirect('/chat-rooms/abc');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_other_users_notification_is_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);

        $this->actingAs($other)->post(route('notifications.markAsRead', $notification->id))->assertForbidden();
    }
}
