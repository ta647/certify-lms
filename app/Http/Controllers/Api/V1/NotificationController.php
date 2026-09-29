<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * TopBar通知ポップオーバー向けのJSON API。Sanctum Cookie認証で保護し、常に自分自身の通知のみを扱う。
 * HTML版(App\Http\Controllers\NotificationController)と役割は同じだが、レスポンス形式のみ異なる。
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(20)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'is_read' => $notification->read_at !== null,
                'time' => $notification->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

        $notification->markAsRead();

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }
}
