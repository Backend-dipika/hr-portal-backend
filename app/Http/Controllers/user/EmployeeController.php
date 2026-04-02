<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{

    public function index()
    {
        try {
            $users = User::with(['designation', 'department', 'employeeOfMonth'])->get();

            return response()->json([
                'status' => true,
                'data' => [ //UserResource::collection($users)
                    'first_name' => $users->pluck('first_name')??'',
                    'last_name' => $users->pluck('last_name')??'',
                    'office_email' => $users->pluck('office_email')??'',
                    'designation' => $users->pluck('designation.name')??'',
                    'department' => $users->pluck('department.name')??'',
                    'employee_of_month' => $users->pluck('employeeOfMonth.month')??'',
                    'sepration_status' => $users->pluck('sepration_status')??'',
                    'sepration_date' => $users->pluck('sepration_date')??'',
                ] 
            ], 200);

        } catch (Exception $e) {
            Log::error('Error fetching employees list', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error occurred while fetching employee list'
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $user = User::with(['designation', 'department', 'employeeOfMonth'])
                ->find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $user //new UserResource($user)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error fetching employee details', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error retrieving employee details'
            ], 500);
        }
    }
}
