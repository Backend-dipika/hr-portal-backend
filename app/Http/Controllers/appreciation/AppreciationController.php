<?php

namespace App\Http\Controllers\appreciation;

use App\Http\Controllers\Controller;
use App\Models\Appreciation;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppreciationController extends Controller
{

    public function sendUsername(Request $request)
    {

        try {
            $user = User::select(['id', 'first_name', 'last_name'])->get();
            return response()->json(['status' => true, 'data' => $user, 'message' => 'Apreciation sent'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error while saving Appreciation', 'error' => $e->getMessage()], 500);
        }
    }
    public function sendAppreiation(Request $request)
    {
        Log::info("send apperection",$request->all());
        // Log::info('Holiday request data:', $request->all());
        $validator = Validator::make($request->all(), [
            'from_user_id' => 'required|exists:users,id',
            'to_user_id' => 'required|exists:users,id',
            'title' => 'nullable|string',
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
            Appreciation::create([
                'uuid' => str::uuid(),
                'from_user_id' => $request->from_user_id,
                'to_user_id' => $request->to_user_id,
                'title' => $request->title,
                'category' => $request->category,
                'message' => $request->message,
                'date_of_appreciation' => Carbon::now(),
            ]);
            return response()->json(['status' => true, 'message' => 'Apreciation sent'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error while saving Appreciation', 'error' => $e->getMessage()], 500);
        }
    }
}
