<?php 

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    /**
     * Store a new leave request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|integer',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:255',
            'half_day_type' => 'nullable|string|in:first,second', // optional for half-day
        ]);

        $user = Auth::user();

        // Calculate total days
        $startDate = new \DateTime($validated['start_date']);
        $endDate   = new \DateTime($validated['end_date']);
        $totalDaysRequested = $endDate->diff($startDate)->days + 1;

        $durationType = null; // default null

        // If leave type is half-day (assuming type_id = 4)
        if ($validated['leave_type_id'] == 4 && !empty($validated['half_day_type'])) {
            $durationType = $validated['half_day_type'] === 'first' ? 'first_half' : 'second_half';
            $totalDaysRequested = 0.5; // half day counts as 0.5
        }

        $leaveRequest = LeaveRequest::create([
            'user_id'              => $user->id,
            'leave_type_id'        => $validated['leave_type_id'],
            'duration_type'        => $durationType,
            'start_date'           => $validated['start_date'],
            'end_date'             => $validated['end_date'],
            'reason'               => $validated['reason'] ?? null,
            'status'               => 'pending',
            'is_cancel_request'    => false,
            'total_days_requested' => $totalDaysRequested,
            'total_days_approved'  => 0,
        ]);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data'    => $leaveRequest,
        ], 201);
    }
}
