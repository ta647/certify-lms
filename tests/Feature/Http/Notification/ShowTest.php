<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AnnouncementNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['notification_type' => 'admin_announcement', 'title' => 'お知らせ', 'message' => '本文', 'url' => '/notifications/x'],
            'read_at' => null,
        ]);
    }

    public function test_owner_can_view_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->get(route('notifications.show', $notification->id));

        $response->assertOk();
        $response->assertSee('お知らせ');
    }

    public function test_other_users_notification_is_forbidden(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);

        $this->actingAs($other)->get(route('notifications.show', $notification->id))->assertForbidden();
    }
}
