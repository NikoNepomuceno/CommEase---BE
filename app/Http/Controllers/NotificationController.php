<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        \Log::info('NotificationController::index called', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_email' => $user->email,
            'user_name' => $user->first_name . ' ' . $user->last_name
        ]);

        $notifications = $user->notifications()
            ->with('event:id,event_title')
            ->orderBy('created_at', 'desc')
            ->limit(50) // Limit to latest 50 notifications
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'message' => $notification->message,
                    'time' => $notification->created_at->diffForHumans(),
                    'time_exact' => $notification->created_at->format('Y-m-d H:i:s'),
                    'read' => !$notification->isUnread(),
                    'event_id' => $notification->event_id,
                    'event_title' => $notification->event?->event_title,
                ];
            });

        // Count unread notifications
        $unreadCount = $user->notifications()
            ->whereNull('read_at')
            ->count();

        \Log::info('Notifications fetched', [
            'user_id' => $user->id,
            'total_notifications' => $notifications->count(),
            'unread_count' => $unreadCount
        ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();
        
        // Check if the notification belongs to the authenticated user
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => [
                'id' => $notification->id,
                'read' => true,
                'read_at' => $notification->read_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $updatedCount = $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $unreadCount = $user->notifications()
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();
        
        // Check if the notification belongs to the authenticated user
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }
}
