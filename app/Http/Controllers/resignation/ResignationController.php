<?php

namespace App\Http\Controllers\resignation;

use App\Http\Controllers\Controller;
use App\Models\ResignationRequest;
use App\Models\ResignationRequestApproval;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ResignationController extends Controller
{

    public function checkIfResigned()
    {
        Log::info('checkIfResigned called');
        try {
            $user = Auth::user();
            // $resignation = ResignationRequest::with(['approvals','approver'])->where('user_id', $user->id)->first();

            $resignation = ResignationRequest::with([
                'approvals' => function ($query) {
                    $query->select('id', 'resignation_request_id', 'approver_id', 'approval_status', 'approval_date')
                        ->with(['approver' => function ($subQuery) {
                            $subQuery->select('id', 'first_name', 'last_name'); // only fetch id & name for approver
                        }]);
                }
            ])
                ->where('user_id', $user->id)->where('final_status', 'pending')
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

    public function showResignedEmployees()
    {
        try {
            $resignation = ResignationRequest::where('approval_status', 'pending')->get();
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
                        'order' => 1,
                    ],
                    [
                        'id' => 1, //CEO ID is 1
                        'order' => 2,
                    ],
                ];
            } else {
                $approvers = [
                    [
                        'id' => 1, //CEO ID is 1
                        'order' => 1,
                    ],
                ];
            }


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
            'employee_id' => 'required|exists:users,id',
            'user_id' => 'required|exists:users,id',
            'resignation_id' => 'required|exists:resignation_requests,id',
            'action' => 'required|in:approve,rejected',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update current approver's response
            $approval = ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
                ->where('approver_id', $request->user_id)
                ->first();

            if (!$approval) {
                return response()->json(['status' => false, 'message' => 'Invalid approver'], 400);
            }

            $approval->update([
                'approval_status' => $request->action,
                'approval_date' => now(),
            ]);

            // Manager step (order 1)
            if ($approval->approval_order == 1) {
                if ($request->action == 'approve') {
                    // activate CEO approval
                    ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
                        ->where('approval_order', 2)
                        ->update(['approval_status' => 'pending']);
                } else {
                    // Manager rejected -> final reject
                    ResignationRequest::where('id', $request->resignation_id)
                        ->update(['final_status' => 'rejected']);
                    User::find($request->employee_id)
                        ->update(['sepration_status' => 'reversed', 'sepration_date' => null]);
                }
            }

            // CEO step (order 2)
            if ($approval->approval_order == 2) {
                if ($request->action == 'approved') {
                    ResignationRequest::where('id', $request->resignation_id)
                        ->update([
                            'final_status' => 'approved',
                            'effective_date' => now(),
                            'notice_period_end_date' => now()->addDays(30),
                        ]);
                    User::find($request->employee_id)
                        ->update(['sepration_status' => 'inactive', 'sepration_date' => now()->addDays(30)]);
                } else {
                    ResignationRequest::where('id', $request->resignation_id)
                        ->update(['final_status' => 'rejected']);
                    User::find($request->employee_id)
                        ->update(['sepration_status' => 'reversed', 'sepration_date' => null]);
                }
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


    // public function responseToResignation(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'employee_id' => 'required|exists:users,id',
    //         'user_id' => 'required|exists:users,id',
    //         'resignation_id' => 'required|exists:resignation_requests,id',
    //         'action' => 'required|in:approve,rejected',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }
    //     try {
    //         ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)
    //             ->where('approver_id', $request->user_id)
    //             ->update([
    //                 'approval_status' => $request->action,
    //                 'approval_date' => now(),
    //                 'updated_at' => now(),
    //             ]);

    //         $isFinalApproval = ResignationRequestApproval::where('resignation_request_id', $request->resignation_id)->where('approval_order', 2)->where('approval_status', 'approved')->exists();
    //         if ($isFinalApproval) {

    //             ResignationRequest::where('id', $request->resignation_id)->update(['final_status' => 'approved', 'effective_date' => now(), 'notice_period_end_date' => now()->addDays(30)]);
    //             User::find($request->employee_id)->update(['sepration_status' => 'inactive', 'sepration_date' => now()]);
    //         } else {
    //             ResignationRequest::where('id', $request->resignation_id)->update(['final_status' => 'rejected']);
    //             User::find($request->employee_id)->update(['sepration_status' => 'reversed', 'sepration_date' => null]);
    //         }
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Responding to resignation successfully',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'An error occurred while responding to resignation',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
