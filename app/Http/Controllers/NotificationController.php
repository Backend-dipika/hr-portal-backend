<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get User Notifications
     *
     * Fetch all notifications for the authenticated user.
     *
     * @group Notifications
     * @authenticated
     *
     * @response 200 {
     *  "success": true,
     *  "data": [
     *    {
     *      "id": "1",
     *      "type": "ExampleNotification",
     *      "message": "You have a new notification",
     *      "is_read": false,
     *      "created_at": "2026-04-08T10:00:00.000000Z"
     *    }
     *  ]
     * }
     */
    public function getNotifications()
    {
        $user = Auth::user();

        $notifications = $user->notifications()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications)
        ]);
    }

    /**
     * Delete Notification
     *
     * Delete a specific notification by ID.
     *
     * @group Notifications
     * @authenticated
     *
     * @urlParam id string required Notification ID.
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Notification deleted successfully"
     * }
     */
    public function deleteNotification($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Mark All Notifications as Read
     *
     * Mark all unread notifications as read for the authenticated user.
     *
     * @group Notifications
     * @authenticated
     *
     * @response 200 {
     *  "success": true,
     *  "message": "All notifications marked as read"
     * }
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }
    
    /**
     * Mark Notification as Read
     *
     * @group Notifications
     * @authenticated
     *
     * @urlParam id string required Notification ID.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }
}
