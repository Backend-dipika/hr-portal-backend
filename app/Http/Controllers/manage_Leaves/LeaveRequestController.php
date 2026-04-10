<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\Holidays;
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
    /**
     * Get Leave Summary for a User
     *
     * Returns the leave balance summary for the authenticated user
     * or for a specific employee (if employee_id is provided).
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @queryParam employee_id integer optional Employee ID (only for Admin/Super Admin). Example: 5
     *
     * @response 200 {
     *   "success": true,
     *   "leaveSummary": [
     *     {
     *       "type": "Paid Leave",
     *       "available": 10,
     *       "annual": 20,
     *       "consumed": 10
     *     }
     *   ]
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     */
    public function leaveSummary(Request $request)  //leave balance summary for the user
    {
        Log::channel('daily')->info("📌 leaveSummary called");

        // Step 1: Get authenticated user
        $user = Auth::user();
        if (!$user) {
            Log::channel('daily')->warning("⚠️ leaveSummary: Unauthenticated access");
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        Log::channel('daily')->info("👤 Authenticated user", ['user_id' => $user->id, 'role' => $user->role->name]);

        // Step 2: Determine employee ID to fetch (prioritize query param)
        $employeeId = $request->query('employee_id') ?? $user->id;
        Log::channel('daily')->info("📌 Employee ID to fetch leave summary", ['employee_id' => $employeeId]);

        // Step 3: Fetch leave balances for the current year only
        $currentYear = now()->year;
        $leaveBalances = LeaveBalance::with('leaveType')
            ->where('user_id', $employeeId)
            ->where('year', $currentYear)
            ->get();

        Log::channel('daily')->info("📌 Fetched leave balances count for year {$currentYear}", ['count' => $leaveBalances->count()]);

        // Step 4: Map balances into structured array
        $leaveSummary = $leaveBalances->map(function ($balance) {
            $mapped = [
                'type' => $balance->leaveType->name ?? 'Unknown',
                'available' => $balance->remaining_days ?? 0,
                'annual' => $balance->total_allocated ?? 0,
                'consumed' => $balance->used_days ?? 0,
            ];
            Log::channel('daily')->info("📄 Leave balance mapped", $mapped);
            return $mapped;
        });

        // Step 5: Log final summary before returning
        Log::channel('daily')->info("✅ Final leave summary prepared", ['summary' => $leaveSummary]);

        return response()->json([
            'success' => true,
            'leaveSummary' => $leaveSummary,
        ]);
    }

    /**
     * Apply for Leave
     *
     * Creates a new leave request for the authenticated user.
     * Includes validation, duplicate checks, leave balance checks,
     * and approval workflow assignment.
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @bodyParam leave_type_id integer required Leave type ID. Example: 1
     * @bodyParam start_date date required Start date of leave. Example: 2026-04-10
     * @bodyParam end_date date required End date of leave. Must be >= start_date. Example: 2026-04-12
     * @bodyParam reason string optional Reason for leave. Example: Personal work
     * @bodyParam half_day_type string optional Required if leave_type_id = 4. Values:first,second. Example: first
     *
     * @response 201 {
     *   "message": "Leave request submitted successfully.",
     *   "data": {
     *     "id": 10,
     *     "user_id": 5,
     *     "leave_type_id": 1,
     *     "start_date": "2026-04-10",
     *     "end_date": "2026-04-12",
     *     "status": "pending",
     *     "total_days_requested": 3,
     *     "total_days_approved": 0
     *   }
     * }
     *
     * @response 400 {
     *   "message": "You already have a leave request for the selected date(s)."
     * }
     *
     * @response 400 {
     *   "message": "No leave balance record found for this leave type."
     * }
     *
     * @response 400 {
     *   "message": "You do not have enough remaining leave balance for this request.",
     *   "remaining_days": 5,
     *   "requested_days": 7
     * }
     *
     * @response 401 {
     *   "message": "Unauthenticated"
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid."
     * }
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

        // ✅ Deduct holidays from total leave days
        $holidayCount = Holidays::whereBetween('start_date', [
            $validated['start_date'],
            $validated['end_date']
        ])
            ->count();

        $totalDaysRequested = max(0, $totalDaysRequested - $holidayCount);

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

                Log::info('Approval created', [
                    'leave_id' => $leaveRequest->id,
                    'approver_id' => $approverId,
                    'level' => $level
                ]);
            }
        }


        return response()->json([
            'message' => 'Leave request submitted successfully.',
            // 'data'    => $leaveRequest,
        ], 201);
    }

    /**
     * Cancel Leave Request
     *
     * Allows an authenticated user to cancel their own leave request.
     *
     * Conditions:
     * - User can only cancel their own leave
     * - Leave must NOT be already:
     *      - Approved
     *      - Rejected
     *      - Cancelled
     *
     * On success:
     * - Leave status is updated to "cancelled"
     * - Pending approvers are notified
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @urlParam leaveId integer required ID of the leave request. Example: 10
     *
     * @response 200 {
     *   "message": "Leave has been cancelled successfully.",
     * }
     *
     * @response 400 {
     *   "message": "Cannot cancel leave that is already approved, rejected, or cancelled."
     * }
     *
     * @response 404 {
     *   "message": "Leave request not found or you are not authorized."
     * }
     *
     * @response 401 {
     *   "message": "Unauthenticated"
     * }
     */
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
            // 'data' => $leaveRequest
        ], 200);
    }

    /**
     * Approve Leave Request
     *
     * Allows an authorized approver to approve a leave request at their assigned level.
     *
     * Workflow:
     * - Each leave has multi-level approvals
     * - Approver can approve only if assigned
     * - If other approvals are pending → partial approval
     * - If all approvals completed → final approval
     *
     * Final Approval Effects:
     * - Deducts leave balance from user's account
     * - Updates leave status to "approved"
     * - Sends notification to user
     *
     * Special Rule:
     * - Half-day leaves always deduct from "Paid Leave"
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @urlParam id integer required Leave request ID. Example: 10
     *
     * @response 200 {
     *   "message": "Leave approved successfully.",
     * }
     *
     * @response 200 {
     *   "message": "Leave approved at your level. Awaiting other approvals.",
     * }
     *
     * @response 400 {
     *   "message": "Leave already processed."
     * }
     *
     * @response 400 {
     *   "message": "Not enough leave balance."
     * }
     *
     * @response 403 {
     *   "message": "No approval record found for your level."
     * }
     *
     * @response 401 {
     *   "message": "Unauthenticated"
     * }
     */
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
                // 'data' => $leaveRequest,
            ]);
        }

        // Final approval → deduct leave balance
        $deductionLeaveTypeId = in_array($leaveRequest->duration_type, ['first_half', 'second_half'])
            ? 1  // Always Paid Leave for half-days
            : $leaveRequest->leave_type_id;

        $year = (int) Carbon::parse($leaveRequest->start_date)->format('Y');

        // $balance = LeaveBalance::firstOrCreate(
        //     [
        //         'user_id' => $leaveRequest->user_id,
        //         'leave_type_id' => $deductionLeaveTypeId,
        //         'year' => $year,
        //     ],
        //     [
        //         'total_allocated' => 0,
        //         'used_days' => 0,
        //         'remaining_days' => 0,
        //         'carry_forward_days' => 0
        //     ]
        // );
        $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
            ->where('leave_type_id', $deductionLeaveTypeId)
            ->where('year', $year)
            ->first();

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
        ]);
    }

    /**
     * Reject Leave Request
     *
     * Allows an authorized approver to reject a leave request.
     *
     * Behavior:
     * - Only pending leave requests can be rejected
     * - Rejecting at any level immediately:
     *      - Marks leave as "rejected"
     *      - Stops further approval flow
     * - Updates approver record (if exists)
     * - Sends rejection notification to user
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @urlParam id integer required Leave request ID. Example: 10
     *
     * @response 200 {
     *   "message": "Leave rejected successfully.",
     * }
     *
     * @response 400 {
     *   "message": "Leave already processed."
     * }
     *
     * @response 403 {
     *   "message": "No approval record found for approver."
     * }
     *
     * @response 401 {
     *   "message": "Unauthenticated"
     * }
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
            // 'data' => $leaveRequest,
        ]);
    }

    /**
     * Get Leave Requests (Role-Based)
     *
     * Fetch leave requests based on the authenticated user's role.
     *
     * - **Admin**: Can view leave requests where they are assigned as Level 2 approver.
     * - **Super Admin**: Can view:
     *      - All Level 1 approvals
     *      - OR leave requests where Level 2 is already approved
     *      - OR leave requests submitted by Admin users
     *
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "employee_name": "John Doe",
     *       "leave_type": "Paid Leave",
     *       "duration_type": "full_day",
     *       "start_date": "01/04",
     *       "end_date": "03/04",
     *       "reason": "Personal work",
     *       "status": "pending",
     *       "total_days_requested": 3,
     *       "total_days_approved": 0,
     *       "created_at": "2026-03-30 10:30",
     *       "approvals": [
     *         {
     *           "id": 10,
     *           "level": 1,
     *           "approver_id": 5,
     *           "status": "pending",
     *           "approved_on": null
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized to view leave requests"
     * }
     *
     * @response 500 {
     *   "success": false,
     *   "message": "Server error while fetching leave requests"
     * }
     */
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
                            'approved_on' => $app->approved_on ? \Carbon\Carbon::parse($app->approved_on)->format('Y-m-d H:i') : "",
                        ]);
                    }
                }

                return [
                    'id' => $leave->id,
                    'employee_name' => trim(($leave->user?->first_name ?? '') . ' ' . ($leave->user?->last_name ?? '')) ?: 'Unknown',
                    'leave_type' => $leave->leaveType?->name ?? "",
                    'duration_type' => $leave->duration_type ?? "",
                    'start_date' => $leave->start_date ? \Carbon\Carbon::parse($leave->start_date)->format('d/m') : "",
                    'end_date' => $leave->end_date ? \Carbon\Carbon::parse($leave->end_date)->format('d/m') : "",
                    'reason' => $leave->reason ?? "",
                    'status' => $leave->status ?? "",
                    'total_days_requested' => $leave->total_days_requested ?? "",
                    'total_days_approved' => $leave->total_days_approved ?? "",
                    'created_at' => $leave->created_at ? \Carbon\Carbon::parse($leave->created_at)->format('Y-m-d H:i') : "",

                    // ⚡ Include approvals for frontend
                    'approvals' => $leave->approvals->map(function ($app) {
                        return [
                            'id' => $app->id,
                            'level' => $app->level,
                            'approver_id' => $app->approver_id,
                            'status' => $app->status,
                            'approved_on' => $app->approved_on ? \Carbon\Carbon::parse($app->approved_on)->format('Y-m-d H:i') : "",
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
                // 'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get User Leave Requests
     *
     * Fetch all leave requests for a specific user.
     *
     * - By default, returns leave requests of the **authenticated user**
     * - **Admin / Super Admin** can fetch leave requests of other users using `employee_id`
     *
     * @group Leave Management
     *
     * @authenticated
     *
     * @queryParam employee_id integer optional Employee ID (Admin/Super Admin only).
     * Example: 5
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "leave_type": "Paid Leave",
     *       "start_date": "2026-04-01",
     *       "end_date": "2026-04-03",
     *       "applied_on": "2026-03-30",
     *       "reason": "Personal work",
     *       "status": "pending",
     *       "total_days_requested": 3,
     *       "total_days_approved": 0,
     *       "approvals": [
     *         {
     *           "level": 1,
     *           "approver_name": "John Doe",
     *           "status": "pending",
     *           "approved_on": null
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     */
    public function userLeaves(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Use query param only if admin or super admin
        $employeeId = $user->role->name === 'Admin' || $user->role->name === 'Super Admin'
            ? $request->query('employee_id') ?? $user->id
            : $user->id;

        $leaves = LeaveRequest::with(['leaveType', 'approvals.approver'])
            ->where('user_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($leave) {
                return [
                    'id' => $leave->id,
                    'leave_type' => $leave->leaveType?->name ?? "",
                    'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                    'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                    'applied_on' => Carbon::parse($leave->created_at)->format('Y-m-d'), // ← only date
                    'reason' => $leave->reason ?? "",
                    'status' => $leave->status,
                    'total_days_requested' => $leave->total_days_requested,
                    'total_days_approved' => $leave->total_days_approved ?? "",
                    'approvals' => $leave->approvals->map(function ($approval) {
                        return [
                            'level' => $approval->level,
                            'approver_name' => trim(($approval->approver?->first_name ?? '') . ' ' . ($approval->approver?->last_name ?? '')) ?: 'Pending',
                            'status' => $approval->status,
                            'approved_on' => $approval->approved_on ? Carbon::parse($approval->approved_on)->format('Y-m-d H:i') : "",
                        ];
                    })->sortBy('level')->values(),
                ];
            });

        return response()->json(['success' => true, 'data' => $leaves]);
    }

    // /**
    //  * Get Leave Status
    //  *
    //  * Fetch detailed information about a specific leave request
    //  * for the authenticated user, including the approval workflow.
    //  *
    //  * - Returns leave details (type, dates, reason, status)
    //  * - Includes multi-level approval chain
    //  * - Ensures user can only access their own leave
    //  *
    //  * @group Leave Management
    //  *
    //  * @authenticated
    //  *
    //  * @urlParam id integer required Leave request ID. Example: 10
    //  *
    //  * @response 200 {
    //  *   "leave_request": {
    //  *     "id": 10,
    //  *     "leave_type": "Paid Leave",
    //  *     "start_date": "2026-04-01",
    //  *     "end_date": "2026-04-03",
    //  *     "reason": "Personal work",
    //  *     "status": "approved",
    //  *     "total_days_requested": 3,
    //  *     "total_days_approved": 3,
    //  *     "approved_on": "2026-04-01 10:30"
    //  *   },
    //  *   "approvals": [
    //  *     {
    //  *       "level": 1,
    //  *       "approver_name": "Manager Name",
    //  *       "status": "approved",
    //  *       "approved_on": "2026-04-01 09:00"
    //  *     },
    //  *     {
    //  *       "level": 2,
    //  *       "approver_name": "Admin Name",
    //  *       "status": "approved",
    //  *       "approved_on": "2026-04-01 10:30"
    //  *     }
    //  *   ]
    //  * }
    //  *
    //  * @response 404 {
    //  *   "message": "Leave not found"
    //  * }
    //  *
    //  * @response 401 {
    //  *   "message": "Unauthenticated"
    //  * }
    //  */
    // public function leaveStatus($id)
    // {
    //     $user = Auth::user();

    //     $leave = LeaveRequest::with(['leaveType', 'approvals.approver'])
    //         ->where('id', $id)
    //         ->where('user_id', $user->id)
    //         ->first();

    //     if (!$leave) {
    //         return response()->json(['message' => 'Leave not found'], 404);
    //     }

    //     return response()->json([
    //         'leave_request' => [
    //             'id' => $leave->id,
    //             'leave_type' => $leave->leaveType?->name ?? "",
    //             'start_date' => Carbon::parse($leave->start_date)->format('Y-m-d'),
    //             'end_date' => Carbon::parse($leave->end_date)->format('Y-m-d'),
    //             'reason' => $leave->reason??"",
    //             'status' => $leave->status,
    //             'total_days_requested' => $leave->total_days_requested,
    //             'total_days_approved' => $leave->total_days_approved,
    //             'approved_on' => $leave->approved_on ? Carbon::parse($leave->approved_on)->format('Y-m-d H:i') : "",
    //         ],
    //         'approvals' => $leave->approvals->map(function ($approval) {
    //             return [
    //                 'level' => $approval->level,
    //                 'approver_name' => trim(($approval->approver?->first_name ?? '') . ' ' . ($approval->approver?->last_name ?? '')) ?: 'Pending',
    //                 'status' => $approval->status,
    //                 'approved_on' => $approval->approved_on ? Carbon::parse($approval->approved_on)->format('Y-m-d H:i') : "",
    //             ];
    //         })->sortBy('level')->values(),
    //     ]);
    // }

    // public function approvalHistory()
    // {
    //     $user = Auth::user();

    //     $leaves = LeaveRequest::with(['user', 'leaveType', 'approvals'])
    //         ->whereHas('approvals', function ($q) use ($user) {
    //             $q->where('approver_id', $user->id);
    //         })
    //         ->orderBy('created_at', 'desc')
    //         ->get()
    //         ->map(function ($leave) {
    //             return [
    //                 'id' => $leave->id,
    //                 'employee_name' => trim(($leave->user?->first_name ?? '') . ' ' . ($leave->user?->last_name ?? '')) ?: 'Unknown',
    //                 'leave_type' => $leave->leaveType?->name ?? 'N/A',
    //                 'start_date' => $leave->start_date ? Carbon::parse($leave->start_date)->format('d/m') : null,
    //                 'end_date' => $leave->end_date ? Carbon::parse($leave->end_date)->format('d/m') : null,
    //                 'status' => $leave->status,
    //                 'approvals' => $leave->approvals->map(function ($app) {
    //                     return [
    //                         'level' => $app->level,
    //                         'approver_name' => trim(($app->approver?->first_name ?? '') . ' ' . ($app->approver?->last_name ?? '')) ?: 'Pending',
    //                         'status' => $app->status,
    //                         'approved_on' => $app->approved_on ? Carbon::parse($app->approved_on)->format('Y-m-d H:i') : null,
    //                     ];
    //                 })->sortBy('level')->values(),
    //             ];
    //         });

    //     return response()->json([
    //         'success' => true,
    //         'data' => $leaves
    //     ]);
    // }
}
