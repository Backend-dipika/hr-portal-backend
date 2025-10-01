<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveYearEndAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\TryCatch;

class YearEndLeaveController extends Controller
{
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
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

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
            }
            return response()->json(['status' => 'success', 'message' => 'Year-end action updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

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
            return response()->json(['status' => 'success', 'data' => $requests], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function saveResponseForEncashment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:leave_year_end_actions,id',
            'action' => 'required|In:approved,rejected',
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
            $leaveYearEndAction->update([
                'is_closed' => true,
                'approval_date' => now(),
                'status' => $request->action
            ]);

            if ($request->action == 'approved') {
                $currentYear = now()->year;

                LeaveBalance::where('user_id', $leaveYearEndAction->user_id)
                    ->where('year', $currentYear)
                    ->update([
                        'carry_forward_days' => $leaveYearEndAction->days,
                        'total_allocated' => LeaveBalance::raw('total_allocated + ' . $leaveYearEndAction->days),
                        'remaining_days' => LeaveBalance::raw('remaining_days + ' . $leaveYearEndAction->days)
                    ]);
            }
            return response()->json(['status' => 'success', 'message' => 'Year-end action updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
