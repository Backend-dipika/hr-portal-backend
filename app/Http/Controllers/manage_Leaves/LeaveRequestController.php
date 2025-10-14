<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Notifications\LeaveApprovalRequestNotification;
use App\Notifications\LeaveStatusNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class LeaveRequestController extends Controller
{
    public function leaveSummary(Request $request)
    {
        $user = $request->user(); // Authenticated user from token/session

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Fetch leave balances with type info
        $leaveSummary = LeaveBalance::with('leaveType')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($balance) {
                return [
                    'type'     => $balance->leaveType->name ?? 'Unknown',
                    'available' => $balance->remaining_days ?? 0,
                    'annual'   => $balance->total_allocated ?? 0,
                    'consumed' => $balance->used_days ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'leaveSummary' => $leaveSummary
        ]);
    }


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

        // ✅ Check duplicate leave request
        $existingLeave = LeaveRequest::where('user_id', $user->id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })
            ->where('status', '!=', 'rejected')
            ->first();

        if ($existingLeave) {
            return response()->json([
                'message' => 'You already have a leave request for the selected date(s).'
            ], 400);
        }
        //to check if the user has remaining day left for the selected leave type
        // ✅ Check available leave balance
        $leaveTypeId = ($validated['leave_type_id'] == 4) ? 1 : $validated['leave_type_id']; // Half-day uses Paid Leave (id = 1)
        $leaveBalance = LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', now()->year)
            ->first();

        if (!$leaveBalance) {
            return response()->json([
                'message' => 'No leave balance record found for this leave type.',
            ], 400);
        }


        $durationType = null;
        $deductionLeaveTypeId = $validated['leave_type_id'];

        // ✅ Handle half-day leave: always deduct from Paid Leave (id = 1)
        if ($validated['leave_type_id'] == 4 && !empty($validated['half_day_type'])) {
            $durationType = $validated['half_day_type'] === 'first' ? 'first_half' : 'second_half';
            $totalDaysRequested = 0.5;
            $deductionLeaveTypeId = 1; // Paid Leave
        } else {
            // ✅ Calculate total weekdays (Mon-Fri)
            $startDate = new \DateTime($validated['start_date']);
            $endDate   = new \DateTime($validated['end_date']);
            $totalDaysRequested = 0;
            $currentDate = clone $startDate;

            while ($currentDate <= $endDate) {
                $dayOfWeek = $currentDate->format('N'); // 1 (Mon) to 7 (Sun)
                if ($dayOfWeek < 6) { // Only Mon-Fri
                    $totalDaysRequested++;
                }
                $currentDate->modify('+1 day');
            }
        }

        // ✅ Ensure requested days do not exceed remaining balance
        if ($totalDaysRequested > $leaveBalance->remaining_days) {
            return response()->json([
                'message' => 'You do not have enough remaining leave balance for this request.',
                'remaining_days' => $leaveBalance->remaining_days,
                'requested_days' => $totalDaysRequested,
            ], 400);
        }


        // ✅ Create leave request
        $leaveRequest = LeaveRequest::create([
            'user_id'              => $user->id,
            'leave_type_id'        => $validated['leave_type_id'],
            'duration_type'        => $durationType,
            'start_date'           => $validated['start_date'],
            'end_date'             => $validated['end_date'],
            'reason'               => $validated['reason'] ?? null,
            'status'               => 'pending', // Always pending initially
            'is_cancel_request'    => false,
            'total_days_requested' => $totalDaysRequested,
            'total_days_approved'  => 0,
        ]);

        // ✅ Get Super Admin
        $superAdmin = \App\Models\User::where('role_id', 1)->first();

        // ✅ Decide approvers based on who is requesting
        $workflowLevels = [];
        if ($user->role_id == 2) {
            // Admin → only Super Admin approves
            $workflowLevels = [1 => $superAdmin?->id];
        } else {
            // Normal user → Super Admin (L1) + Reporting Manager (L2)
            $workflowLevels = [
                1 => $superAdmin?->id,
                2 => $user->reporting_manager_id,
            ];
        }

        // ✅ Create approval records (all pending)
        foreach ($workflowLevels as $level => $approverId) {
            if ($approverId) {
                LeaveApproval::create([
                    'leave_request_id' => $leaveRequest->id,
                    'approver_id'      => $approverId,
                    'level'            => $level,
                    'status'           => 'pending',
                    'action_type'      => 'leave_approval',
                ]);

                // Send notification
                $approver = \App\Models\User::find($approverId);
                if ($approver) {
                    $approver->notify(new LeaveApprovalRequestNotification($leaveRequest));
                }
            }
        }

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data'    => $leaveRequest,
        ], 201);
    }



    public function cancelLeave(Request $request, $leaveId)
    {
        $user = Auth::user();
        Log::info('Cancel leave request started', [
            'leave_id' => $leaveId,
            'user_id' => $user->id
        ]);

        // Find the leave request owned by this user
        $leaveRequest = LeaveRequest::where('id', $leaveId)
            ->where('user_id', $user->id)
            ->first();

        if (!$leaveRequest) {
            Log::warning('Leave request not found or unauthorized', [
                'leave_id' => $leaveId,
                'user_id' => $user->id
            ]);
            return response()->json([
                'message' => 'Leave request not found or you are not authorized.'
            ], 404);
        }
        Log::info('Leave request found', [
            'leave_id' => $leaveRequest->id,
            'status' => $leaveRequest->status
        ]);

        // Check if leave is already approved or rejected
        if (in_array($leaveRequest->status, ['approved', 'rejected', 'cancelled'])) {
            Log::warning('Cannot cancel leave already approved, rejected or cancelled', [
                'leave_id' => $leaveRequest->id,
                'status' => $leaveRequest->status
            ]);
            return response()->json([
                'message' => 'Cannot cancel leave that is already approved, rejected, or cancelled.'
            ], 400);
        }

        // Mark as cancelled
        $leaveRequest->is_cancel_request = true; // optional, keeps track of user request
        $leaveRequest->status = 'cancelled';
        $leaveRequest->save();

        Log::info('Leave marked as cancelled', [
            'leave_id' => $leaveRequest->id,
            'status' => $leaveRequest->status,
            'is_cancel_request' => $leaveRequest->is_cancel_request
        ]);

        // Optionally notify approvers about cancellation
        $approvals = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->get();

        foreach ($approvals as $approval) {
            $approver = \App\Models\User::find($approval->approver_id);
            if ($approver) {
                $approver->notify(new LeaveStatusNotification($leaveRequest, 'cancelled'));
            }
        }

        Log::info('Leave cancellation process completed', [
            'leave_id' => $leaveRequest->id,
            'user_id' => $user->id
        ]);

        return response()->json([
            'message' => 'Leave has been cancelled successfully.',
            'data' => $leaveRequest
        ], 200);
    }

    public function approveLeave(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $approver = Auth::user();

        // Prevent approving a leave already finalized
        if (in_array($leaveRequest->status, ['approved', 'rejected'])) {
            return response()->json(['message' => 'Leave already processed.'], 400);
        }

        // Check approver level
        $approval = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('approver_id', $approver->id)
            ->first();

        if (!$approval) {
            return response()->json(['message' => 'No approval record found for your level.'], 403);
        }

        if (in_array($approval->status, ['approved', 'rejected'])) {
            return response()->json(['message' => 'You have already processed this request.'], 400);
        }

        // Approve current level
        $approval->status = 'approved';
        $approval->approved_on = now();
        $approval->save();

        // Check if any approvals are still pending
        $anyPending = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->exists();

        if ($anyPending) {
            return response()->json([
                'message' => 'Leave approved at your level. Awaiting other approvals.',
                'data' => $leaveRequest,
            ]);
        }

        // Final approval → deduct leave balance
        $deductionLeaveTypeId = in_array($leaveRequest->duration_type, ['first_half', 'second_half'])
            ? 1  // Always Paid Leave for half-days
            : $leaveRequest->leave_type_id;

        $year = (int) Carbon::parse($leaveRequest->start_date)->format('Y');

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $leaveRequest->user_id,
                'leave_type_id' => $deductionLeaveTypeId,
                'year' => $year,
            ],
            [
                'total_allocated' => 0,
                'used_days' => 0,
                'remaining_days' => 0,
                'carry_forward_days' => 0
            ]
        );

        $daysToDeduct = (float) $leaveRequest->total_days_requested;

        if ($balance->remaining_days < $daysToDeduct) {
            return response()->json(['message' => 'Not enough leave balance.'], 400);
        }

        $balance->used_days = round($balance->used_days + $daysToDeduct, 2);
        $balance->remaining_days = round($balance->remaining_days - $daysToDeduct, 2);
        $balance->save();


        // Update leave request
        $leaveRequest->status = 'approved';
        $leaveRequest->total_days_approved = $daysToDeduct;
        $leaveRequest->approved_on = now();
        $leaveRequest->save();

        // Notify user
        $leaveRequest->user->notify(new LeaveStatusNotification($leaveRequest, 'Approved'));

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
        Log::info("Starting leave rejection process", [
            'leave_request_id' => $id,
            'approver_id' => Auth::id()
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);
        $approver = Auth::user();

        Log::info("Fetched leave request for rejection", [
            'leave_request_id' => $leaveRequest->id,
            'current_status' => $leaveRequest->status,
            'request_user_id' => $leaveRequest->user_id
        ]);

        // Prevent rejecting already finalized leave
        if ($leaveRequest->status !== 'pending') {
            Log::warning("Attempt to reject already finalized leave request", [
                'leave_request_id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'approver_id' => $approver->id
            ]);

            return response()->json(['message' => 'Leave already processed.'], 400);
        }

        // Update current approver record if exists
        $approval = LeaveApproval::where('leave_request_id', $leaveRequest->id)
            ->where('status', 'pending')
            ->where('approver_id', $approver->id)
            ->first();

        if ($approval) {
            $approval->status = 'rejected';
            $approval->action_type = 'cancel_leave_approval';
            $approval->approved_on = now();
            $approval->save();

            Log::info("Approver record updated to rejected", [
                'approval_id' => $approval->id,
                'approver_id' => $approver->id,
                'leave_request_id' => $leaveRequest->id,
                'approved_on' => $approval->approved_on
            ]);
        } else {
            Log::warning("No matching approval record found for approver", [
                'leave_request_id' => $leaveRequest->id,
                'approver_id' => $approver->id
            ]);
        }

        // Reject the entire leave request immediately
        $leaveRequest->status = 'rejected';
        $leaveRequest->approved_on = now();
        $leaveRequest->total_days_approved = 0;
        $leaveRequest->save();

        Log::info("Leave request finalized as rejected", [
            'leave_request_id' => $leaveRequest->id,
            'approved_on' => $leaveRequest->approved_on
        ]);

        $leaveRequest->user->notify(new LeaveStatusNotification($leaveRequest, 'Rejected'));
        Log::info("Leave rejection notification sent", [
            'leave_request_id' => $leaveRequest->id,
            'notified_user_id' => $leaveRequest->user_id
        ]);

        return response()->json([
            'message' => 'Leave rejected successfully.',
            'data' => $leaveRequest,
        ]);
    }

    public function index()
    {
        // Basic request info
        Log::info('[leaves.index] called', [
            'ip' => request()->ip(),
            'route' => Route::currentRouteName() ?? 'n/a',
            'method' => request()->method(),
            'user_agent' => request()->header('User-Agent'),
            'authorization_present' => request()->header('Authorization') ? true : false,
        ]);

        // Auth check
        $user = Auth::user();
        if (!$user) {
            Log::error('[leaves.index] no authenticated user (Auth::user() returned null)');
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        Log::info('[leaves.index] authenticated user', [
            'id' => $user->id ?? null,
            'email' => $user->email ?? null,
            'role' => $user->role ?? null,
        ]);

        try {
            // Build base query
            $query = LeaveRequest::with(['user', 'leaveType', 'approvals'])
                ->orderBy('created_at', 'desc');

            Log::info('[leaves.index] base query prepared (with user, leaveType, approvals)');

            // Role-based filtering: use the role name
            $roleName = $user->role->name ?? null;

            if ($roleName === 'Admin') {
                Log::info('[leaves.index] ROLE = Admin; fetching level 2 pending approvals');
                $query->whereHas('approvals', function ($q) use ($user) {
                    $q->where('level', 2)
                        ->where('approver_id', $user->id);
                });
            } elseif ($roleName === 'Super Admin') {
                Log::info('[leaves.index] ROLE = Super Admin');

                $query->whereHas('approvals', function ($q) {
                    $q->where('level', 1);
                });

                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereHas('approvals', function ($q) {
                            $q->where('level', 2)->where('status', 'approved');
                        })
                        ->orWhereHas('user.role', function ($q) {
                            $q->where('name', 'Admin');
                        });
                });
            } elseif ($roleName === 'Reporting Manager') {
                Log::info('[leaves.index] ROLE = Reporting Manager; fetching level 1 approvals assigned to RM');
                $query->whereHas('approvals', function ($q) use ($user) {
                    $q->where('level', 1)
                        ->where('approver_id', $user->id);
                });
            } else {
                Log::warning('[leaves.index] user role not allowed', [
                    'role' => $roleName,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view leave requests',
                ], 403);
            }

            // Log SQL and bindings
            try {
                Log::info('[leaves.index] query SQL and bindings', [
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                ]);
            } catch (\Throwable $t) {
                Log::warning('[leaves.index] unable to capture SQL', ['error' => $t->getMessage()]);
            }

            // Execute query
            DB::enableQueryLog();
            Log::info('[leaves.index] executing query->get() now');
            $fetched = $query->get();

            Log::info('[leaves.index] executed DB queries', [
                'count' => count(DB::getQueryLog()),
                'queries' => DB::getQueryLog(),
            ]);

            Log::info('[leaves.index] fetched rows', ['count' => $fetched->count()]);

            if ($fetched->isEmpty()) {
                Log::info('[leaves.index] no leave requests returned for this user/role');
            }

            // Map response including approvals
            $requests = $fetched->map(function ($leave) {
                Log::info('[leaves.index] formatting leave row', [
                    'leave_id' => $leave->id,
                    'user_id' => $leave->user_id,
                    'status' => $leave->status,
                    'approvals_count' => $leave->approvals?->count() ?? 0,
                ]);

                if ($leave->approvals && $leave->approvals->isNotEmpty()) {
                    foreach ($leave->approvals as $app) {
                        Log::info('[leaves.index] approval detail', [
                            'leave_id' => $leave->id,
                            'approval_id' => $app->id ?? null,
                            'level' => $app->level ?? null,
                            'approver_id' => $app->approver_id ?? null,
                            'status' => $app->status ?? null,
                            'approved_on' => $app->approved_on ? \Carbon\Carbon::parse($app->approved_on)->format('Y-m-d H:i') : null,
                        ]);
                    }
                }

                return [
                    'id' => $leave->id,
                    'employee_name' => trim(($leave->user?->first_name ?? '') . ' ' . ($leave->user?->last_name ?? '')) ?: 'Unknown',
                    'leave_type' => $leave->leaveType?->name ?? 'N/A',
                    'duration_type' => $leave->duration_type,
                    'start_date' => $leave->start_date ? \Carbon\Carbon::parse($leave->start_date)->format('d/m') : null,
                    'end_date' => $leave->end_date ? \Carbon\Carbon::parse($leave->end_date)->format('d/m') : null,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'total_days_requested' => $leave->total_days_requested,
                    'total_days_approved' => $leave->total_days_approved,
                    'created_at' => $leave->created_at ? \Carbon\Carbon::parse($leave->created_at)->format('Y-m-d H:i') : null,

                    // ⚡ Include approvals for frontend
                    'approvals' => $leave->approvals->map(function ($app) {
                        return [
                            'id' => $app->id,
                            'level' => $app->level,
                            'approver_id' => $app->approver_id,
                            'status' => $app->status,
                            'approved_on' => $app->approved_on ? \Carbon\Carbon::parse($app->approved_on)->format('Y-m-d H:i') : null,
                        ];
                    }),
                ];
            });

            Log::info('[leaves.index] mapping completed', ['mapped_count' => $requests->count()]);

            return response()->json([
                'success' => true,
                'data' => $requests,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[leaves.index] exception while fetching leaves', [
                'message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error while fetching leave requests. Check server logs for details.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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

    public function approvalHistory()
    {
        $user = Auth::user();

        $leaves = LeaveRequest::with(['user', 'leaveType', 'approvals'])
            ->whereHas('approvals', function ($q) use ($user) {
                $q->where('approver_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($leave) {
                return [
                    'id' => $leave->id,
                    'employee_name' => trim(($leave->user?->first_name ?? '') . ' ' . ($leave->user?->last_name ?? '')) ?: 'Unknown',
                    'leave_type' => $leave->leaveType?->name ?? 'N/A',
                    'start_date' => $leave->start_date ? Carbon::parse($leave->start_date)->format('d/m') : null,
                    'end_date' => $leave->end_date ? Carbon::parse($leave->end_date)->format('d/m') : null,
                    'status' => $leave->status,
                    'approvals' => $leave->approvals->map(function ($app) {
                        return [
                            'level' => $app->level,
                            'approver_name' => trim(($app->approver?->first_name ?? '') . ' ' . ($app->approver?->last_name ?? '')) ?: 'Pending',
                            'status' => $app->status,
                            'approved_on' => $app->approved_on ? Carbon::parse($app->approved_on)->format('Y-m-d H:i') : null,
                        ];
                    })->sortBy('level')->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $leaves
        ]);
    }
}
