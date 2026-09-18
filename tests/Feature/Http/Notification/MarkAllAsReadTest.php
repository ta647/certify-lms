<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_unread_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'chat_message_received', 'title' => 'A', 'message' => '', 'url' => '/x'],
            'read_at' => null,
        ]);
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ChatMessageReceivedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'chat_message_received', 'title' => 'B', 'message' => '', 'url' => '/y'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
