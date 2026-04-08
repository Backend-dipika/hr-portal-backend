<?php

namespace App\Http\Controllers;

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
     *
     * @authenticated
     *
     * @response 200 [
     *  {
     *    "id": "1",
     *    "type": "App\\Notifications\\ExampleNotification",
     *    "notifiable_type": "App\\Models\\User",
     *    "notifiable_id": 1,
     *    "data": {
     *      "message": "You have a new notification"
     *    },
     *    "read_at": null,
     *    "created_at": "2026-04-08T10:00:00.000000Z",
     *    "updated_at": "2026-04-08T10:00:00.000000Z"
     *  }
     * ]
     *
     * @response 401 {
     *  "message": "Unauthenticated."
     * }
     */
    public function getNotifications()
    {
        $user = Auth::user();

        $notifications = $user->notifications()->latest()->get();

        return response()->json($notifications);
    }

    // public function getUnreadNotifications()
    // {
    //     $user = Auth::user();

    //     $unread = $user->unreadNotifications()->latest()->get();

    //     return response()->json($unread);
    // }

    // public function markAsRead($id)
    // {
    //     $user = Auth::user();

    //     $notification = $user->notifications()->where('id', $id)->first();
    //     if ($notification) {
    //         $notification->markAsRead();
    //     }

    //     return response()->json(['status' => 'success']);
    // }

}
