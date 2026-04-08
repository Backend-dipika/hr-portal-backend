<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveYearEndAction;
use App\Models\User;
use App\Notifications\EncashmentRequestNotification;
use App\Notifications\EncashmentStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\TryCatch;

class YearEndLeaveController extends Controller
{
    /**
     * Check Year-End Leave Process Eligibility
     *
     * Determine whether the authenticated user has any pending
     * year-end leave actions to process.
     *
     * - Returns eligible (submitted but not closed) record
     * - Returns all past processed records
     *
     * @group Year-End Leave Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Year-end process check completed.",
     *   "data": {
     *     "eligible": {
     *       "id": 1,
     *       "days": 5,
     *       "status": "submitted",
     *       "is_closed": false
     *     },
     *     "all_records": []
     *   }
     * }
     *
     * @response 500 {
     *   "status": "false",
     *   "message": "An error occurred while checking year-end process."
     * }
     */
    public function checkIfYearEndProcessNeeded()
    {
        try {
            $user = Auth::user();
            // leave which are need to be closed for the user
            $isEligible = LeaveYearEndAction::where('user_id', $user->id)
                ->where('status', '=',  'submitted')
                ->where('is_closed', false)
                ->first();

            $allRecords = LeaveYearEndAction::where('user_id', $user->id)->where('status', '!=',  'submitted')->get();
            Log::info('Is Eligible:', $isEligible ? $isEligible->toArray() : ['No record found']);

            // Log all records
            Log::info('All Records:', $allRecords->toArray());
            return response()->json([
                'status' => 'success',
                'message' => 'Year-end process check completed.',
                'data' => [
                    'eligible' => $isEligible,
                    'all_records' => $allRecords,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => 'An error occurred while checking year-end process.'], 500);
        }
    }

    /**
     * Update Year-End Leave Action
     *
     * Process year-end leave action for an employee.
     *
     * - Option: carry_forward → adds leave balance
     * - Option: encash → sends request to admin for approval
     *
     * @group Year-End Leave Management
     *
     * @authenticated
     *
     * @bodyParam id integer required Leave Year-End Action ID. Example: 10
     * @bodyParam option string required Action type (carry_forward or encash). Example: carry_forward
     * @bodyParam remarks string optional Remarks for action. Example: Carry forward remaining leaves
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Year-end action updated successfully."
     * }
     *
     * @response 422 {
     *   "status": "error",
     *   "message": ["The option field is required."]
     * }
     *
     * @response 404 {
     *   "status": "error",
     *   "message": "Leave year-end action not found."
     * }
     */
    public function updateYearEndAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:leave_year_end_actions,id',
            'remarks' => 'nullable|string|max:255',
            'option' => 'required|In:carry_forward,encash',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->all()], 422);
        }
        try {
            $leaveYearEndAction = LeaveYearEndAction::find($request->id);
            if (!$leaveYearEndAction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Leave year-end action not found.'
                ], 404);
            }
            $currentYear = now()->year;
            if ($request->option == 'carry_forward') {
                LeaveBalance::where('user_id', $leaveYearEndAction->user_id)
                    ->where('year', $currentYear)
                    ->where('leave_type_id', 1)
                    ->update([
                        'carry_forward_days' => $leaveYearEndAction->days,
                        'total_allocated' => LeaveBalance::raw('total_allocated + ' . $leaveYearEndAction->days),
                        'remaining_days' => LeaveBalance::raw('remaining_days + ' . $leaveYearEndAction->days)
                    ]);

                $leaveYearEndAction->update([
                    'is_closed' => true,
                    'remarks' => $request->remarks,
                    'approval_date' => now(),
                    'action_type' => $request->option,
                    'status' => 'approved'
                ]);
            } else {
                LeaveYearEndAction::where('id', $request->id)
                    ->update([
                        // 'is_closed' => true,
                        'remarks' => $request->remarks,
                        'processed_on' => now(),
                        'action_type' => $request->option,
                        'status' => 'pending'
                    ]);

                $employee = User::select('first_name', 'last_name')
                    ->where('id', $leaveYearEndAction->user_id)
                    ->first();

                $employeeName = trim($employee->first_name . ' ' . $employee->last_name);

                $admin = User::where('role_id', 1)->first();
                $admin->notify(new EncashmentRequestNotification($employeeName));
            }
            return response()->json(['status' => 'success', 'message' => 'Year-end action updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Pending Year-End Approval Requests
     *
     * Fetch all pending encashment requests that require admin approval.
     *
     * - Includes employee details (department, designation)
     * - Only returns pending & not closed records
     *
     * @group Year-End Leave Management
     *
     * @authenticated
     *
     * @response 200 {
     *   "status": "true",
     *   "data": [
     *     {
     *       "id": 1,
     *       "days": 5,
     *       "status": "pending",
     *       "user": {
     *         "first_name": "John",
     *         "last_name": "Doe",
     *         "department": {
     *           "name": "IT"
     *         },
     *         "designation": {
     *           "name": "Developer"
     *         }
     *       }
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": "false",
     *   "message": "An error occurred while fetching approval requests."
     * }
     */
    public function showApprovalRequests()
    {
        try {

            $requests = LeaveYearEndAction::with([
                'user:id,first_name,last_name,department_id,designation_id',
                'user.department:id,name',
                'user.designation:id,name'
            ])
                ->where('status', '=', 'pending')
                ->where('is_closed', false)
                ->get();
            Log::info('[YearEndLeaveController.showApprovalRequests] fetched rows', ['count' => $requests]);
            return response()->json(['status' => 'true', 'data' => $requests], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => 'An error occurred while fetching approval requests.'], 500);
        }
    }

    /**
     * Respond to Encashment Request
     *
     * Allows admin to approve or reject leave encashment requests.
     *
     * - Approved → marks request as closed and notifies employee
     * - Rejected → converts leave into carry forward
     *
     * @group Year-End Leave Management
     *
     * @authenticated
     *
     * @bodyParam id integer required Leave Year-End Action ID. Example: 10
     * @bodyParam action string required Action to take (approved or rejected). Example: approved
     * @bodyParam amount number optional Encashment amount. Example: 5000
     *
     * @response 200 {
     *   "status": "success",
     *   "message": "Year-end action updated successfully."
     * }
     *
     * @response 422 {
     *   "status": "false",
     *   "message": ["The action field is required."]
     * }
     *
     * @response 404 {
     *   "status": "error",
     *   "message": "Leave year-end action not found."
     * }
     *
     * @response 500 {
     *   "status": "false",
     *   "message": "An error occurred while updating year-end action."
     * }
     */
    public function saveResponseForEncashment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:leave_year_end_actions,id',
            'action' => 'required|In:approved,rejected',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'false', 'message' => $validator->errors()->all()], 422);
        }
        try {
            $leaveYearEndAction = LeaveYearEndAction::find($request->id);
            if (!$leaveYearEndAction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Leave year-end action not found.'
                ], 404);
            }
            $leaveYearEndAction->update([
                'is_closed' => true,
                'approval_date' => now(),
                'status' => $request->action
            ]);
            $currentYear = now()->year;
            if ($request->action == 'approved') {


                // LeaveBalance::where('user_id', $leaveYearEndAction->user_id)
                //     ->where('year', $currentYear)
                //     ->where('leave_type_id', 1)
                //     ->update([
                //         'carry_forward_days' => $leaveYearEndAction->days,
                //         'total_allocated' => LeaveBalance::raw('total_allocated + ' . $leaveYearEndAction->days),
                //         'remaining_days' => LeaveBalance::raw('remaining_days + ' . $leaveYearEndAction->days)
                //     ]);
                $leaveYearEndAction->update([
                    'is_closed' => true,
                    // 'remarks' => $request->remarks,
                    'approval_date' => now(),
                    // 'action_type' => $request->option,
                    'status' => 'approved'
                ]);
                $employee = User::where('id', $leaveYearEndAction->user_id)->first();
                $employee->notify(new EncashmentStatusNotification('approved', $request->amount));
            } else {
                LeaveBalance::where('user_id', $leaveYearEndAction->user_id)
                    ->where('year', $currentYear)
                    ->where('leave_type_id', 1)
                    ->update([
                        'carry_forward_days' => $leaveYearEndAction->days,
                        'total_allocated' => LeaveBalance::raw('total_allocated + ' . $leaveYearEndAction->days),
                        'remaining_days' => LeaveBalance::raw('remaining_days + ' . $leaveYearEndAction->days)
                    ]);
                $leaveYearEndAction->update([
                    'is_closed' => true,
                    // 'remarks' => $request->remarks,
                    'approval_date' => now(),
                    // 'action_type' => $request->option,
                    'status' => 'rejected'
                ]);
                $employee = User::where('id', $leaveYearEndAction->user_id)->first();
                $employee->notify(new EncashmentStatusNotification('rejected', $request->amount));
            }
            return response()->json(['status' => 'success', 'message' => 'Year-end action updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'false', 'message' => 'An error occurred while updating year-end action.'], 500);
        }
    }
}
