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

class HolidayController extends Controller
{
    /**
     * Add Holidays (Bulk)
     *
     * Create one or multiple holidays in a single request.
     *
     * - Accepts an array of holidays
     * - Automatically determines the day (Monday, Tuesday, etc.)
     * - Stores each holiday with a unique UUID
     *
     * @group Holiday Management
     *
     * @authenticated
     *
     * @bodyParam holidays array required List of holidays to add.
     * @bodyParam holidays[].date date required Holiday date (YYYY-MM-DD). Example: 2026-01-26
     * @bodyParam holidays[].name string required Holiday name. Example: Republic Day
     *
     *  Example:
     * [
     *   {
     *     "date": "2026-01-26",
     *     "name": "Republic Day"
     *   },
     *   {
     *     "date": "2026-08-15",
     *     "name": "Independence Day"
     *   }
     * ]
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Holidays added successfully"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "holidays.0.date": ["The date field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "An error occurred while responding to holidays creation"
     * }
     */
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
                // 'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Holiday List (Current Year)
     *
     * Fetch all holidays for the current year.
     *
     * - Filters holidays based on current year
     * - Sorted by date (ascending)
     *
     * @group Holiday Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Republic Day",
     *       "day": "Monday",
     *       "start_date": "2026-01-26"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "An error occurred fetching holidays details"
     * }
     */
    public function showHolidayList()
    {
        try {
            $currentYear = Carbon::now()->year;

            $holidays = Holidays::whereYear('start_date', $currentYear)
                ->orderBy('start_date', 'asc')
                ->get();


            return response()->json([
                'status' => true,
                'data' => $holidays,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred fetching holidays details',
                // 'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Holiday
     *
     * Delete a holiday by its ID.
     *
     * @group Holiday Management
     *
     * @authenticated
     *
     * @urlParam id integer required Holiday ID. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Holiday deleted successfully"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Holiday not found"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "An error occurred while deleting the holiday"
     * }
     */
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
                // 'error' => $e->getMessage(),
                'status' => false,
                'message' => 'An error occurred while deleting the holiday',

            ], 500);
        }
    }

    /**
     * Update Holiday
     *
     * Update an existing holiday's details.
     *
     * Features:
     * - Updates holiday name and date
     * - Automatically recalculates the day (Monday, Tuesday, etc.)
     *
     * @group Holiday Management
     *
     * @authenticated
     *
     * @bodyParam id integer required Holiday ID. Example: 1
     * @bodyParam start_date date required New holiday date. Example: "2026-01-26"
     * @bodyParam name string required Holiday name. Example: "Republic Day"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Holiday updated successfully"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "id": ["The selected id is invalid."]
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Holiday not found"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "An error occurred while updating the holiday"
     * }
     */
    public function updateHoliday(Request $request)
    {
        Log::info('Update Holiday request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:holidays,id',
            'start_date' => 'required|date',
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

            $date = Carbon::parse($request->start_date);
            $dayName = $date->format('l');

            $holiday->update([
                'name' => $request->name,
                'day' => $dayName,
                'start_date' => $request->start_date,
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
                // 'error' => $e->getMessage()
            ], 500);
        }
    }
}
