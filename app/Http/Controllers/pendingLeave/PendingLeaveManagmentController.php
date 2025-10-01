<?php

namespace App\Http\Controllers\pendingLeave;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PendingLeaveManagmentController extends Controller
{
    public function showUserPendingLeaves()
    {
        try {
            $user = Auth::user();

            return response()->json(['status' => 'success', 'data' => $user->pendingLeaves], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
