<?php

namespace App\Http\Controllers\holiday;

use App\Http\Controllers\Controller;
use App\Models\Holidays;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HolidaysController extends Controller
{
    public function addHolidays(Request $request)
    {
        Log::info('Holiday request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'holidays' => 'required|array|min:1',
            'holidays.*.date' => 'required|date',
            'holidays.*.name' => 'required|string',
            // 'holidays.*.year' => 'required|digits:4|integer'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->holidays as $holiday) {

                $date = Carbon::parse($holiday['date']);
                $dayName = $date->format('l');
                Holidays::create([
                    'uuid' => Str::uuid(),
                    'name' => $holiday['name'],
                    'day' => $dayName,
                    'start_date' => $holiday['date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Holidays added successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while responding to holidays creation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showHolidayList()
    {
        try {
            $currentYear = Carbon::now()->year;

            $holidays = Holidays::whereYear('start_date', $currentYear)->get();

            return response()->json([
                'status' => true,
                'data' => $holidays,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred fetching holidays details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteHoliday($id)
    {
        try {
            $holiday = Holidays::find($id);

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found',
                ], 404);
            }

            $holiday->delete();

            return response()->json([
                'status' => true,
                'message' => 'Holiday deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'status' => false,
                'message' => 'An error occurred while deleting the holiday',

            ], 500);
        }
    }

    public function updateHoliday(Request $request)
    {
        Log::info('Update Holiday request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:holidays,id',
            'date' => 'required|date',
            'name' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $holiday = Holidays::find($request->id);

            if (!$holiday) {
                return response()->json([
                    'status' => false,
                    'message' => 'Holiday not found',
                ], 404);
            }

            $date = Carbon::parse($request->date);
            $dayName = $date->format('l');

            $holiday->update([
                'name' => $request->name,
                'day' => $dayName,
                'start_date' => $request->date,
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Holiday updated successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating the holiday',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
