<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{


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
