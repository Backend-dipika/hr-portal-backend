<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
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
            'half_day_type' => 'nullable|string|in:first,second',
        ]);

        $user = Auth::user();

        // Calculate total days
        $startDate = new \DateTime($validated['start_date']);
        $endDate   = new \DateTime($validated['end_date']);
        $totalDaysRequested = $endDate->diff($startDate)->days + 1;

        $durationType = null;

        // Handle half-day leave
        if ($validated['leave_type_id'] == 4 && !empty($validated['half_day_type'])) {
            $durationType = $validated['half_day_type'] === 'first' ? 'first_half' : 'second_half';
            $totalDaysRequested = 0.5;
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

        // Get Super Admin ID (first user with role_id = 1)
        $superAdmin = \App\Models\User::where('role_id', 1)->first();

        // Get reporting manager ID from relation
        $reportingManagerId = $user->reporting_manager_id;

        // Workflow levels with approver IDs
        $workflowLevels = [
            1 => $superAdmin?->id,
            2 => $reportingManagerId,
        ];

        foreach ($workflowLevels as $level => $approverId) {
            if ($approverId) { // only create if approver exists
                LeaveApproval::create([
                    'leave_request_id' => $leaveRequest->id,
                    'approver_id'      => $approverId,
                    'level'            => $level,
                    'status'           => 'pending',
                    'action_type'      => 'leave_approval',
                ]);
            }
        }

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data'    => $leaveRequest,
        ], 201);
    }

    /**
     * Approve a leave request (per level)
     */
    public function approveLeave(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $approver = Auth::user();

        // Prevent approving a leave already finalized
        if (in_array($leaveRequest->status, ['approved', 'rejected'])) {
            return response()->json(['message' => 'Leave already processed.'], 400);
        }

        // Find or create pending approval for this approver
        $approval = LeaveApproval::firstOrCreate(
            [
                'leave_request_id' => $leaveRequest->id,
                'approver_id' => $approver->id,
                'status' => 'pending',
            ],
            [
                'level' => 1, // default level if not provided
                'action_type' => 'leave_approval',
            ]
        );

        // Approve current level
        $approval->status = 'approved';
        $approval->approved_on = now();
        $approval->save();

        // Check if any approvals are still pending
        $anyPending = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->exists();

        if (!$anyPending) {
            // All levels approved → finalize leave request
            $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', Carbon::now()->year)
                ->first();

            if (!$balance) {
                return response()->json(['message' => 'No leave balance found.'], 404);
            }

            if ($balance->remaining_days < $leaveRequest->total_days_requested) {
                return response()->json(['message' => 'Not enough leave balance.'], 400);
            }

            $balance->used_days += $leaveRequest->total_days_requested;
            $balance->remaining_days -= $leaveRequest->total_days_requested;
            $balance->save();

            $leaveRequest->status = 'approved';
            $leaveRequest->total_days_approved = $leaveRequest->total_days_requested;
            $leaveRequest->approved_on = now();
            $leaveRequest->save();
        }

        return response()->json([
            'message' => 'Leave approved successfully.',
            'data' => $leaveRequest,
        ]);
    }


    /**
     * Reject a leave request (any level)
     */
    public function rejectLeave(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $approver = Auth::user();

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Leave already processed.'], 400);
        }

        // Update current approver record
        $approval = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->where('approver_id', $approver->id)
            ->first();

        if ($approval) {
            $approval->status = 'rejected';
            $approval->action_type = 'leave_rejection';
            $approval->approved_on = now();
            $approval->save();
        }

        // Reject the entire leave request immediately
        $leaveRequest->status = 'rejected';
        $leaveRequest->approved_on = now();
        $leaveRequest->total_days_approved = 0;
        $leaveRequest->save();

        return response()->json([
            'message' => 'Leave rejected successfully.',
            'data' => $leaveRequest,
        ]);
    }

    /**
     * List all leave requests
     */
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
                    'start_date' => Carbon::parse($request->start_date)->format('d/m'),
                    'end_date'   => Carbon::parse($request->end_date)->format('d/m'),
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'total_days_requested' => $request->total_days_requested,
                    'total_days_approved' => $request->total_days_approved,
                    'created_at' => $request->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    
    /**
     * List leave requests of the authenticated user
     */
    public function userLeaves()
    {
        $user = Auth::user();

        $leaves = LeaveRequest::with(['leaveType', 'approvals.approver'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($leave) {
                return [
                    'id' => $leave->id,
                    'leave_type' => $leave->leaveType?->name ?? 'N/A',
                    'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'total_days_requested' => $leave->total_days_requested,
                    'total_days_approved' => $leave->total_days_approved,
                    'approvals' => $leave->approvals->map(function ($approval) {
                        return [
                            'level' => $approval->level,
                            'approver_name' => trim(($approval->approver?->first_name ?? '') . ' ' . ($approval->approver?->last_name ?? '')) ?: 'Pending',
                            'status' => $approval->status,
                            'approved_on' => $approval->approved_on ? Carbon::parse($approval->approved_on)->format('Y-m-d H:i') : null,
                        ];
                    })->sortBy('level')->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $leaves,
        ]);
    }

    /**
     * Show status of a single leave request for authenticated user
     */
    public function leaveStatus($id)
    {
        $user = Auth::user();

        $leave = LeaveRequest::with(['leaveType', 'approvals.approver'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$leave) {
            return response()->json(['message' => 'Leave not found'], 404);
        }

        return response()->json([
            'leave_request' => [
                'id' => $leave->id,
                'leave_type' => $leave->leaveType?->name ?? 'N/A',
                'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                'reason' => $leave->reason,
                'status' => $leave->status,
                'total_days_requested' => $leave->total_days_requested,
                'total_days_approved' => $leave->total_days_approved,
                'approved_on' => $leave->approved_on ? Carbon::parse($leave->approved_on)->format('Y-m-d H:i') : null,
            ],
            'approvals' => $leave->approvals->map(function ($approval) {
                return [
                    'level' => $approval->level,
                    'approver_name' => trim(($approval->approver?->first_name ?? '') . ' ' . ($approval->approver?->last_name ?? '')) ?: 'Pending',
                    'status' => $approval->status,
                    'approved_on' => $approval->approved_on ? Carbon::parse($approval->approved_on)->format('Y-m-d H:i') : null,
                ];
            })->sortBy('level')->values(),
        ]);
    }
}
