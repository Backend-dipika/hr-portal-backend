<?php

namespace App\Http\Controllers\resignation;

use App\Http\Controllers\Controller;
use App\Models\ResignationRequest;
use App\Models\ResignationRequestApproval;
use App\Models\User;
use App\Notifications\ResignationSentNotification;
use App\Notifications\ResignationStatusNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ResignationController extends Controller
{

    public function checkIfResigned() //to show resignation for or related details on frontend
    {
        Log::info('checkIfResigned called');
        try {
            $user = Auth::user();
            $resignation = ResignationRequest::with([
                'approvals' => function ($query) {
                    $query->select('id', 'resignation_request_id', 'approver_id', 'approval_status', 'approval_date')
                        ->with(['approver' => function ($subQuery) {
                            $subQuery->select('id', 'first_name', 'last_name'); // only fetch id & name for approver
                        }]);
                }
            ])
                ->where('user_id', $user->id)
                ->whereIn('final_status', ['pending', 'approved'])
                ->select('id', 'user_id', 'type', 'submission_date', 'final_status', 'notice_period_end_date')
                ->first();


            if (!$resignation) {
                return response()->json([
                    'status' => true,
                    'message' => 'No resignation found for the user',
                    'data' => null
                ]);
            }
            return response()->json([
                'status' => true,
                'message' => 'Employee Resigned fetched successfully',
                'data' => $resignation
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching resigned employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function  showResignedEmployees()
    {
        try {
            Log::info("show resigned employee called");
            $resignation = ResignationRequest::with([
                'user' => function ($query) { //who has resigned
                    $query->select('id', 'first_name', 'last_name', 'office_email', 'department_id')
                        ->with(['department:id,name']); // fetch dept name
                },
                'approvals' => function ($query) { //how many approval records
                    $query->select('id', 'resignation_request_id', 'approver_id', 'approval_status', 'approval_order', 'approval_date')
                        ->with(['approver' => function ($subQuery) { //who will approve
                            $subQuery->select('id', 'first_name', 'last_name', 'office_email');
                        }]);
                }
            ])
                ->where('final_status', 'pending')
                ->select('id', 'user_id', 'type', 'submission_date', 'final_status', 'notice_period_end_date')
                ->get();


            Log::info('resigned Employees', ['resign' => $resignation]);
            return response()->json([
                'status' => true,
                'message' => 'Resigned employees fetched successfully',
                'data' => $resignation
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching resigned employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function initiateResignation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'resigned_on' => 'required|date',
            'reason' => 'required|string',
            'message' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|', //mimes:pdf,doc,docx|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $user = User::find($request->user_id);

            $resignation =   ResignationRequest::create([
                'user_id' => $request->user_id,
                'requested_by_id' => $request->user_id,
                'type' => 'resignation',
                'submission_date' => $request->resigned_on,
                'effective_date' => $request->resigned_on,
                'notice_period_end_date' => Carbon::parse($request->resigned_on)->addMonths(1),
                'reason' => $request->reason,
                'message' => $request->message,
                'final_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
                // 'document' => $request->hasFile('attachment') ? $request->file('attachment')->store('resignations', 'public') : null,
            ]);
            if ($request->hasFile('attachment')) {
                $path = "resignations/{$resignation->id}";
                $file = $request->file('attachment');
                Storage::disk('public')->putFileAs($path, $file, $file->getClientOriginalName());
                $resignation->document = "storage/{$path}/{$file->getClientOriginalName()}";
                $resignation->save();
            }

            if ($user->reporting_manager_id) {
                $approvers = [
                    [
                        'id' => $user->reporting_manager_id, // Default to 1 if no manager
                        'order' => 1, //manger
                    ],
                    [
                        'id' => 1, //CEO ID is 1
                        'order' => 2, //ceo
                    ],
                ];
            } else {
                $approvers = [
                    [
                        'id' => 1, //CEO ID is 1
                        'order' => 1, //ceo if manger has applied 
                    ],
                ];
            }

            if ($user->reporting_manager_id) {
                $manger = User::find($user->reporting_manager_id);
                $manger->notify(new ResignationSentNotification(
                    $user->first_name . ' ' . $user->last_name,
                    $user->personal_email,
                    $request->expected_last_working_date,
                    $request->message
                ));
            } else {
                $ceo = User::find(1);
                $ceo->notify(new ResignationSentNotification(
                    $user->first_name . ' ' . $user->last_name,
                    $user->personal_email,
                    $request->expected_last_working_date,
                    $request->message
                ));
            }
            //   $manger= User::find($request->user_id)


            foreach ($approvers as $approver) {
                ResignationRequestApproval::create([
                    'resignation_request_id' => $resignation->id,
                    'approver_id' => $approver['id'],
                    'approval_order' => $approver['order'],
                    'approval_status' => $approver['order'] === 1 ? 'pending' : 'waiting',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            User::find($request->user_id)
                ->update(['sepration_status' => 'resigned']);




            return response()->json([
                'status' => true,
                'message' => 'Resignation initiated successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while initiating resignation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function responseToResignation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resignation_id' => 'required|exists:resignation_requests,id',
            'employee_id' => 'required|exists:users,id',
            'action' => 'required|in:approved,rejected',
            'approver_id' => 'required|exists:users,id',
            'approval_order' => 'required|in:1,2',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update the approver’s decision
            ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
                ->where('approver_id', $request->approver_id)
                ->where('approval_order', $request->approval_order)
                ->update(['approval_status' => $request->action, 'approval_date' => Carbon::now(),]);

            $employee = User::find($request->employee_id);

            // $ceo = User::find(1);
            // $ceo->notify(new ResignationSentNotification(
            //     $ceo->first_name . ' ' . $ceo->last_name,
            //     $ceo->personal_email,
            //     $request->expected_last_working_date,
            //     $request->message
            // )); 
            if ($request->action === 'approved') {

                $isSecond = ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
                    ->where('approval_order', 2)->first();

                if ($isSecond && $request->approval_order == 1) {
                    // Manager approved → unlock CEO row (set to pending)
                    $isSecond->update(['approval_status' => 'pending']);
                    $ceo = User::find(1);
                    $ceo->notify(new ResignationSentNotification(
                        $employee->first_name . ' ' . $employee->last_name,
                        $employee->personal_email,
                        $request->expected_last_working_date,
                        $request->message
                    ));
                } else {
                    // ResignationRequest::where('id', $request->resignation_id)
                    //     ->update(['final_status' => 'approved']);

                    // User::where('id', $request->employee_id)
                    //     ->update([
                    //         'sepration_status' => 'resigned',
                    //         'sepration_date' => Carbon::now()->addMonths(3)
                    //     ]);
                    // }

                    // // Only CEO (order 2) finalizes approval
                    // if ($request->approval_order == 2) {
                    ResignationRequest::where('id', $request->resignation_id)
                        ->update(['final_status' => 'approved']);

                    User::where('id', $request->employee_id)
                        ->update([
                            'sepration_status' => 'resigned',
                            'sepration_date' => Carbon::now()->addMonths(3)
                        ]);
                }


                // APPROVED mail
                $employee->notify(new ResignationStatusNotification(
                    $employee->first_name . ' ' . $employee->last_name,
                    'approved',
                    null,
                    $employee->sepration_date
                ));
            } else {
                // If rejected → mark final status immediately
                ResignationRequest::where('id', $request->resignation_id)
                    ->update(['final_status' => 'cancelled']);

                User::where('id', $request->employee_id)
                    ->update(['sepration_status' => 'reversed']);

                $employee->notify(new ResignationStatusNotification(
                    $employee->first_name . ' ' . $employee->last_name,
                    'rejected',
                    'Your resignation cannot be accepted.
                    Futher discussion will be done with your respected lead and shivanand sir.'
                ));
            }

            return response()->json([
                'status' => true,
                'message' => 'Responded to resignation successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while responding to resignation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelResignation(Request $request)
    {
        Log::info('incomng request', $request->all());
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'resignation_id' => 'required|exists:resignation_requests,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            ResignationRequest::where('id', $request->resignation_id)
                ->where('user_id', $request->user_id)
                ->update(['final_status' => 'cancelled', 'updated_at' => now()]);

            ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
                ->delete();

            User::find($request->user_id)
                ->update(['sepration_status' => 'active', 'sepration_date' => null]);

            return response()->json([
                'status' => true,
                'message' => 'Resignation cancelled successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while cancelling resignation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
