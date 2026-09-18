<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

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

    public function test_user_can_view_own_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->createNotification($user);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('タイトル');
    }

    public function test_unread_tab_excludes_read_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->createNotification($user, read: true);

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertDontSee('タイトル');
    }

    public function test_admin_has_no_notifications(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewHas('unreadCount', 0);
    }
}
