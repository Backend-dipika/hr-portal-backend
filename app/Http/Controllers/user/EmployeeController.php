<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{

    /**
     * Get Employees List
     *
     * Fetch all employees with basic details like name, email, designation, department,
     * and separation information.
     *
     * @group Employee
     *
     * @response 200 {
     *   "status": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "uuid": "be762142-95f0-45a6-aa31-023e9a8fe1d0",
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "office_email": "john.doe@company.com",
     *       "office_id": "EMP001",
     *       "designation": "Backend Developer",
     *       "department": "IT",
     *       "employee_of_month": "March",
     *       "sepration_status": 0,
     *       "sepration_date": null
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error occurred while fetching employee list"
     * }
     */

    public function index()
    {
        try {
            $users = User::with(['designation', 'department', 'employeeOfMonth'])->get();


            $data = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name ?? '',
                    'last_name' => $user->last_name ?? '',
                    'office_email' => $user->office_email ?? '',
                    'office_id' => $user->office_id ?? '',
                    'designation' => $user->designation?->name ?? '',
                    'department' => $user->department?->name ?? '',
                    'employee_of_month' => $user->employeeOfMonth?->first()?->month ?? '',
                    'sepration_status' => $user->sepration_status ?? '',
                    'sepration_date' => $user->sepration_date ?? '',
                ];
            });

            return response()->json([
                'status' => true,
                'data' => $data
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

    /**
     * Get Employee Details
     *
     * Fetch detailed information of a specific employee using UUID.
     * Includes designation, department, address, documents, employee type,
     * and reporting manager details.
     *
     * @group Employee
     *
     * @urlParam uuid string required Employee UUID. Example: be762142-95f0-45a6-aa31-023e9a8fe1d0
     *
     * @response 200 {
     *   "status": true,
     *   "data": {
     *     "id": 1,
     *     "uuid": "be762142-95f0-45a6-aa31-023e9a8fe1d0",
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "office_email": "john.doe@company.com",
     *     "role": {
     *         "id": 3,
     *         "name": "Employee"
     *     },
     *     "designation": {
     *       "id": 1,
     *       "name": "Backend Developer"
     *     },
     *     "department": {
     *       "id": 2,
     *       "name": "IT"
     *     },
     *     "employee_type": {
     *       "id": 1,
     *       "name": "Full-time"
     *     },
     *     "reporting_manager": {
     *       "id": 2,
     *       "first_name": "Jane",
     *       "last_name": "Smith"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Employee not found"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error retrieving employee details"
     * }
     */
    public function show($uuid)
    {
        try {
            $user = User::with(['role', 'designation', 'department', 'employeeOfMonth', 'address',  'employeeType', 'reportingManager', 'teamMembers.designation'])
                ->where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => new UserResource($user)
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
