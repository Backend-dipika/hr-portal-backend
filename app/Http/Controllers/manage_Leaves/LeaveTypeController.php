<?php

namespace App\Http\Controllers\manage_Leaves;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveTypeController extends Controller
{
    public function index()
    {
        try {
            $leaveTypes = LeaveType::all();
            return response()->json(['data' => $leaveTypes, 'message' => 'Leave Type fetched successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching leave types.', 'message' => $e->getMessage()], 500);
        }
    }

    public function showPendingLeavesOfAllEmployees()
    {
        try {

            $leaveBalances = LeaveBalance::with([
                'user:id,first_name,last_name,office_id,department_id,designation_id',
                'user.department:id,name',
                'user.designation:id,name',
                'leaveType:id,name,is_paid,code'
            ])->get();

            // Group leave balances by user
            $leavesByUser = $leaveBalances->groupBy('user_id')->map(function ($balances, $userId) {
                $user = $balances->first()->user;

                // Prepare leave balances without nested user to avoid repetition
                // $balancesData = $balances->map(function ($b) {
                //     return [
                //         'id' => $b->id,
                //         'year' => $b->year,
                //         'total_allocated' => $b->total_allocated,
                //         'used_days' => $b->used_days,
                //         'remaining_days' => $b->remaining_days,
                //         'carry_forward_days' => $b->carry_forward_days,
                //         'created_at' => $b->created_at,
                //         'updated_at' => $b->updated_at
                //     ];
                // });

                // Calculate pending leaves by type
                $pendingPaidLeave = $balances
                    ->where('leave_type_id', 1) // Paid Leave
                    ->sum(fn($b) => $b->total_allocated - $b->used_days);

                $pendingCompOff = $balances
                    ->where('leave_type_id', 2) // Comp-off
                    ->sum(fn($b) => $b->total_allocated - $b->used_days);

                return [
                    'user' => $user,
                    'pending_paid_leave' => $pendingPaidLeave,
                    'pending_compoff' => $pendingCompOff,
                    // 'leave_balances' => $balancesData
                ];
            });

            return response()->json([
                'data' => $leavesByUser,
                'message' => 'Pending leaves calculated successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching leave types.', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'              => 'required|string|max:255',
                'type'              => 'required|string|max:255',
                'code'              => 'nullable|string|max:50',
                'max_allowed_days'  => 'required|integer|min:0',
                'is_paid' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $leaveType = LeaveType::create($validator->validated());

            return response()->json([
                'message'   => 'Leave type created successfully',
                'leaveType' => $leaveType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'                => 'required|integer|exists:leave_types,id',
                'name'              => 'sometimes|required|string|max:255',
                'type'              => 'sometimes|required|string|max:255',
                'code'              => 'nullable|string|max:50',
                'max_allowed_days'  => 'sometimes|required|integer|min:0',
                'is_paid' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }
            $leaveType = LeaveType::findOrFail($request->id);
            $leaveType->update($validator->validated());

            return response()->json([
                'message'   => 'Leave type updated successfully',
                'leaveType' => $leaveType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $leaveType = LeaveType::findOrFail($id);
            $leaveType->delete();

            return response()->json([
                'message' => 'Leave type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
