<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveApproval;
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

        // Create leave request
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

        // Create approval record
        LeaveApproval::create([
            'leave_request_id' => $leaveRequest->id,
            'approver_id'      => null, // must be valid or nullable
            'level'            => 1,
            'status'           => 'pending',
            'action_type'      => 'leave_approval', // ✅ matches enum
        ]);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data'    => $leaveRequest,
        ], 201);
    }

    public function index()
    {
        $requests = LeaveRequest::with(['user', 'leaveType'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'employee_name' => trim(
                        $request->user?->first_name . ' ' . $request->user?->last_name
                    ) ?: 'Unknown',
                    'leave_type' => $request->leaveType?->name ?? 'N/A',
                    'duration_type' => $request->duration_type,
                    // ✅ Format dates to YYYY-MM-DD
'start_date' => \Carbon\Carbon::parse($request->start_date)->format('d/m'),
'end_date'   => \Carbon\Carbon::parse($request->end_date)->format('d/m'),


                    'reason' => $request->reason,
                    'status' => $request->status,
                    'total_days_requested' => $request->total_days_requested,
                    'total_days_approved' => $request->total_days_approved,
                    // ✅ Optionally also format created_at
                    'created_at' => $request->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }
}
