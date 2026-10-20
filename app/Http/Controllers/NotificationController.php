<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'message', 'link', 'type', 'read', 'created_at']);

        return response()->json([
            'notifications' => $notifications,
            'unread' => $notifications->where('read', false)->count(),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 403);

        $notification->update(['read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['success' => true]);
    }
}
