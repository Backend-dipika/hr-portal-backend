<?php

namespace App\Http\Controllers\attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProcessedAttendance;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Get attendance records for employees
     *
     * This API fetches attendance data based on:
     * - Yesterday attendance
     * - Date range attendance
     * - Specific employee attendance
     * - Today's attendance (default)
     *
     * @group Attendance Management
     *
     * @authenticated
     *
     * @queryParam yesterday boolean optional Fetch yesterday attendance. Example: true
     * @queryParam employee_id integer optional Employee/User ID. Example: 101
     * @queryParam from_date date optional Start date in Y-m-d format. Example: 2026-05-01
     * @queryParam to_date date optional End date in Y-m-d format. Example: 2026-05-20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Attendance sent successfully for all employees",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 101,
     *       "employee_name": "John Doe",
     *       "attendance_date": "2026-05-20",
     *       "checkin_time": "09:15:10",
     *       "checkout_time": "18:30:25",
     *       "status": "Present",
     *       "created_at": "2026-05-20T09:15:10.000000Z",
     *       "updated_at": "2026-05-20T18:30:25.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "An error occurred while sending attendance"
     * }
     */
    public function getEmployeeAttendance(Request $request)
    {

        try {
            $query = ProcessedAttendance::query();
            if ($request->filled('yesterday')) {
                $query->whereDate('attendance_date', Carbon::yesterday());
            } else if ($request->filled('from_date') && $request->filled('to_date') || $request->filled('employee_id')) {
               if($request->filled('employee_id')) {
                    $query->where('user_id', $request->employee_id);
                }
                if($request->filled('from_date') && $request->filled('to_date')) {
                    $query->whereBetween('attendance_date', [$request->from_date, $request->to_date]);
                }
            } else {
                $query->whereDate(
                    'attendance_date',
                    Carbon::today()
                );
            }
            $attendanceData = $query->get();
            $attendanceData->transform(function ($attendance) {

                $attendance->status =
                    ($attendance->checkin_time && $attendance->checkout_time)
                    ? 'Present'
                    : 'Incomplete';

                return $attendance;
            });

            return response()->json([
                'status' => true,
                'message' => 'Attendance sent successfully for all employees',
                'data' => $attendanceData
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in sending attendance: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while sending attendance'
            ], 500);
        }
    }

    /**
     * Get logged-in user's attendance
     *
     * This API fetches attendance records of the authenticated user.
     * Filters supported:
     * - Date range
     * - Month & Year
     * - Current month attendance (default)
     *
     * @group Attendance Management
     *
     * @authenticated
     *
     * @queryParam from_date date optional Start date in Y-m-d format. Example: 2026-05-01
     * @queryParam to_date date optional End date in Y-m-d format. Example: 2026-05-20
     * @queryParam month integer optional Month number. Example: 5
     * @queryParam year integer optional Year number. Example: 2026
     * @queryParam per_page integer optional Pagination size. Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Self attendance fetched successfully",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "user_id": 101,
     *         "employee_name": "John Doe",
     *         "attendance_date": "2026-05-20",
     *         "checkin_time": "09:05:00",
     *         "checkout_time": "18:20:00",
     *         "status": "Present",
     *         "created_at": "2026-05-20T09:05:00.000000Z",
     *         "updated_at": "2026-05-20T18:20:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Something went wrong"
     * }
     */
    public function getSelfAttendance(Request $request)
    {
        try {

            // Logged in user
            $user = auth()->user();

            // Correct user id
            $userId = $user->office_id;

            $query = ProcessedAttendance::where('user_id', $userId);

            // Date range filter
            if ($request->filled('from_date') && $request->filled('to_date')) {

                $query->whereBetween('attendance_date', [
                    $request->from_date,
                    $request->to_date
                ]);
            }

            // Month + Year filter
            elseif ($request->filled('month') && $request->filled('year')) {

                $query->whereMonth('attendance_date', $request->month)
                    ->whereYear('attendance_date', $request->year);
            }

            // Default current month
            else {

                $query->whereMonth('attendance_date', now()->month)
                    ->whereYear('attendance_date', now()->year);
            }

            $attendanceData = $query
                ->orderBy('attendance_date', 'desc')
                ->paginate($request->per_page ?? 20);

            $attendanceData->getCollection()->transform(function ($attendance) {

                $attendance->status =
                    ($attendance->checkin_time && $attendance->checkout_time)
                    ? 'Present'
                    : 'Incomplete';

                return $attendance;
            });

            return response()->json([
                'status' => true,
                'message' => 'Self attendance fetched successfully',
                'data' => $attendanceData
            ], 200);
        } catch (\Exception $e) {

            Log::error('Get Self Attendance Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get Employee List
     *
     * This API fetches the complete list of employees.
     *
     * @group Attendance Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Employee list fetched successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 101,
     *       "employee_name": "John Doe",
     *       "created_at": "2026-05-20T10:00:00.000000Z",
     *       "updated_at": "2026-05-20T10:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 102,
     *       "employee_name": "Jane Smith",
     *       "created_at": "2026-05-20T10:00:00.000000Z",
     *       "updated_at": "2026-05-20T10:00:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Something went wrong"
     * }
     */
    public function getEmployeeList(Request $request)
    {
        try {
            $employee = Employee::all();

            return response()->json([
                'status' => true,
                'message' => 'Employee list fetched successfully',
                'data' => $employee
            ], 200);
        } catch (Exception $e) {
            Log::error('Get Employee List Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }
}
