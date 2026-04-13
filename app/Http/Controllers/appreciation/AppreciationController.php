<?php

namespace App\Http\Controllers\appreciation;

use App\Http\Controllers\Controller;
use App\Models\Appreciation;
use App\Models\User;
use App\Notifications\AppreciationNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppreciationController extends Controller
{
    /**
     * Get Users List (For Appreciation)
     *
     * Fetch a list of all users with basic details.
     * This API is typically used to populate dropdowns/select lists
     * when sending appreciation.
     *
     * Returns:
     * - User ID
     * - First name
     * - Last name
     *
     * @group Appreciation Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "first_name": "Aarti",
     *       "last_name": "Sharma"
     *     },
     *     {
     *       "id": 2,
     *       "first_name": "Rahul",
     *       "last_name": "Verma"
     *     }
     *   ],
     *   "message": "Apreciation sent"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error while saving Appreciation"
     * }
     */

    public function sendUsername(Request $request)
    {

        try {
            $user = User::select(['id', 'first_name', 'last_name'])->get();
            return response()->json(['status' => true, 'data' => $user, 'message' => 'Apreciation sent'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error sending list'], 500);
        }
    }

    /**
     * Send Appreciation
     *
     * Allows a user to send appreciation to another user.
     *
     * - Stores appreciation details
     * - Sends notification to recipient user
     * - Supports optional title, category, and message
     *
     * @group Appreciation Management
     *
     * @authenticated
     *
     * @bodyParam to_user_id integer required Receiver user ID. Example: 2
     * @bodyParam title string required Appreciation title. Example: "Great Work!"
     * @bodyParam category string optional Category of appreciation. Example: "Teamwork"
     * @bodyParam message string optional Appreciation message. Example: "Amazing collaboration on the project!"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Apreciation sent"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "to_user_id": ["The to user id field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error while saving Appreciation"
     * }
     */
    public function sendAppreciation(Request $request)
    {
        // Log::info("send apperection", $request->all());
        // Log::info('Holiday request data:', $request->all());
        $validator = Validator::make($request->all(), [
            // 'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'category' => 'nullable|string',
            'message' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $user = Auth::user();
            $fromUserId = $user->id;
            if ($fromUserId == $request->to_user_id) {
                return response()->json(['message' => 'You cannot appreciate yourself'], 400);
            }
            Appreciation::create([
                'uuid' => str::uuid(),
                'from_user_id' => $fromUserId,
                'to_user_id' => $request->to_user_id,
                'title' => $request->title,
                'category' => $request->category,
                'message' => $request->message,
                'date_of_appreciation' => Carbon::now(),
            ]);
            $toUser = User::find($request->to_user_id);
            if (!$toUser) {
                throw new \Exception("Recipient user not found for ID {$request->to_user_id}");
            }
            $messageData = [
                'from_user_id' => $fromUserId,
                'to_user_id' => $request->to_user_id,
                'category' => $request->category,
                'title' => $request->title,
                'message' => $request->message,
            ];
            $toUser->notify(new AppreciationNotification($messageData));
            return response()->json(['status' => true, 'message' => 'Appreciation  sent'], 200);
        } catch (Exception $e) {
            Log::error('Error while sending appreciation', [
                'message' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            return response()->json(['status' => false, 'message' => 'Error while saving Appreciation'], 500);
        }
    }
}
