<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function showbirthdayAnniversaries()
    {
        try {
            $today = Carbon::today();
            $tenDaysLater = $today->copy()->addDays(10);
            // birthdays
            $birthdays = User::whereNotNull('date_of_birth')
                ->whereRaw("TO_CHAR(date_of_birth, 'MM-DD') BETWEEN ? AND ?", [
                    $today->format('m-d'),
                    $tenDaysLater->format('m-d')
                ])
                ->get(['id', 'first_name', 'last_name', 'profile_picture', 'date_of_birth']);

            // anniversaries
            $anniversaries = User::whereNotNull('date_of_joining')
                ->whereRaw("TO_CHAR(date_of_joining, 'MM-DD') BETWEEN ? AND ?", [
                    $today->format('m-d'),
                    $tenDaysLater->format('m-d')
                ])
                ->get(['id', 'first_name', 'last_name', 'profile_picture', 'date_of_joining']);


            return response()->json([
                'status' => 'success',
                'birthdays' => $birthdays,
                'anniversaries' => $anniversaries
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showOffThisWeekEmployees()
    {
        try {
            // Start and end of current week (Monday → Sunday)
            $startOfWeek = Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString(); // "2025-09-29"
            $endOfWeek = Carbon::today()->endOfWeek(Carbon::FRIDAY)->toDateString(); // "2025-10-05"


            // Fetch leave requests overlapping with this week and approved
            $employeesOffThisWeek = LeaveRequest::with('user:id,first_name,last_name,profile_picture')
                ->where('status', 'approved')
                ->where(function ($query) use ($startOfWeek, $endOfWeek) {
                    $query->whereBetween('start_date', [$startOfWeek, $endOfWeek])
                        ->orWhereBetween('end_date', [$startOfWeek, $endOfWeek])
                        ->orWhere(function ($q) use ($startOfWeek, $endOfWeek) {
                            // Leave starts before this week and ends after this week
                            $q->where('start_date', '<', $startOfWeek)
                                ->where('end_date', '>', $endOfWeek);
                        });
                })
                ->get();

            return response()->json([
                'status' => 'success',
                'on_leave' => $employeesOffThisWeek
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showStatsComponentData()
    {
        try {
            $today = Carbon::today()->toDateString(); 
            $usersCount = User::count();
            $approvedLeavesCount = LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count();
            $departmentsCount = Department::count();
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_employees' => $usersCount,
                    'approved_leaves' => $approvedLeavesCount,
                    'departments' => $departmentsCount
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
