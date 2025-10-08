<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use App\Models\Address;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeType;
use App\Models\User;
use App\Models\UserDocuments;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class RegistrationController extends Controller
{
    public function sendFormOptions()
    {
        try {
            $departments = Department::select('id', 'name')->get();
            $designations = Designation::select('id', 'name')->get();
            $employee_types = EmployeeType::select('id', 'name')->get();
            $reporting_managers = User::select('id', 'first_name', 'last_name')
                ->where('role_id', 2) //  role_id 2 is for Admin
                ->where('is_disable', false)
                ->get();

            return response()->json([
                'status' => true,
                'departments' => $departments,
                'designations' => $designations,
                'employee_types' => $employee_types,
                'reporting_managers' => $reporting_managers,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch form options', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while fetching form options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function savePersonalInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emp_id' => 'nullable|exists:users,id',
            'salutation' => 'nullable|string',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|max:10',
            'personal_email' => ['required', 'email', 'max:255', Rule::unique('users', 'personal_email')->ignore($request->emp_id)],
            'phone_no' => ['required', 'string', 'regex:/^[0-9]{10,15}$/', Rule::unique('users', 'phone_no')->ignore($request->emp_id)],
            'alt_phone_no' => ['nullable', 'string', 'regex:/^[0-9]{10,15}$/', Rule::unique('users', 'alt_phone_no')->ignore($request->emp_id)],
            'date_of_birth' => 'required|date|before:today',
            'marital_status' => 'nullable|string|max:50',
            'blood_grp' => 'nullable|string|max:5',
            'specially_abled' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $user = User::where('id', $request->emp_id)
                ->where('is_disable', false)
                ->first();

            if ($user) {
                // Update existing user
                User::where('id', $request->emp_id)->update([
                    'salutation' => $request->salutation,
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name ?? null,
                    'last_name' => $request->last_name,
                    'gender' => $request->gender,
                    'personal_email' => $request->personal_email,
                    'phone_no' => $request->phone_no,
                    'alt_phone_no' => $request->alt_phone_no,
                    'date_of_birth' => $request->date_of_birth,
                    'marital_status' => $request->marital_status,
                    'blood_grp' => $request->blood_grp ?? null,
                    'specially_abled' => $request->specially_abled ?? null,
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'uuid' => Str::uuid(),
                    'salutation' => $request->salutation,
                    'first_name' => $request->first_name,
                    'middle_name' => $request->middle_name ?? null,
                    'last_name' => $request->last_name,
                    'gender' => $request->gender,
                    'personal_email' => $request->personal_email,
                    'phone_no' => $request->phone_no,
                    'alt_phone_no' => $request->alt_phone_no,
                    'role_id' => 3,
                    'date_of_birth' => $request->date_of_birth,
                    'marital_status' => $request->marital_status,
                    'blood_grp' => $request->blood_grp ?? null,
                    'specially_abled' => $request->specially_abled ?? null,
                    'is_disable' => false,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => ' personal info saved successfully',
                'emp_id' => $request->emp_id ?? $user->id
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to save personal info', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while saving personal info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emp_id' => 'required|exists:users,id',
            'addresses' => 'required|array',
            'addresses.*.type' => 'required|in:permanent,current',
            'addresses.*.address1' => 'required|string|max:255',
            'addresses.*.address2' => 'nullable|string|max:255',
            'addresses.*.city' => 'required|string|max:100',
            'addresses.*.state' => 'required|string|max:100',
            'addresses.*.pincode' => 'required|string|max:10',
            'addresses.*.country' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Ensure addresses is an array
            $addresses = is_string($request->addresses)
                ? json_decode($request->addresses, true)
                : $request->addresses;

            foreach ($addresses as $address) {
                Address::updateOrCreate(
                    [
                        'user_id' => $request->emp_id,
                        'type'    => $address['type'], // condition: same user + same type
                    ],
                    [
                        'uuid'    => Str::uuid(), // keep generating new uuid if inserted
                        'address1' => $address['address1'],
                        'address2' => $address['address2'] ?? null,
                        'city'     => $address['city'],
                        'state'    => $address['state'],
                        'pincode'  => $address['pincode'],
                        'country'  => $address['country'],
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Addresses saved successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error occurred while saving Address Info', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while saving Address Info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveEmploymentDetails(Request $request)
    {
        Log::info('Employeement Request Data:', $request->all());
        $validator = Validator::make($request->all(), [
            'emp_id' => 'required|exists:users,id',
            'office_id' => 'nullable|string|max:255|unique:users,office_id',
            // 'office_email' => 'nullable|email|max:255|unique:users,office_email',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'date_of_joining' => 'nullable|date',
            'employee_type_id' => 'nullable|exists:employee_types,id',
            'reporting_manager_id' => 'nullable|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            User::where('id', $request->emp_id,)->update([
                'office_id' => $request->office_id,
                'office_email' => $request->office_email,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'date_of_joining' => $request->date_of_joining,
                'probation_end_date' => Carbon::parse($request->date_of_joining)->addMonths(3),
                'employee_type_id' => $request->employee_type_id,
                'reporting_manager_id' => $request->reporting_manager_id ?? null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Employment details saved successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error occurred while saving Address Info', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while saving Address Info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emp_id' => 'required|exists:users,id',
            'adhar_card' => 'nullable|file|mimes:jpeg,png|max:3000',
            'pan_card' => 'nullable|file|mimes:jpeg,png|max:3000',
            'certificate' => 'nullable|file|mimes:jpeg,png|max:3000',
            'experience_letter' => 'nullable|file|mimes:jpeg,png|max:3000',
            'salary_slip' => 'nullable|file|mimes:jpeg,png,pdf',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {

            $path = "user/{$request->emp_id}";

            // Handle file uploads if provided
            foreach (['adhar_card', 'pan_card', 'certificate', 'experience_letter', 'salary_slip'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    Storage::disk('public')->putFileAs($path, $file, $file->getClientOriginalName());

                    // Save the storage path for DB
                    $filePaths[$field] = "storage/{$path}/{$file->getClientOriginalName()}";
                } else {
                    $filePaths[$field] = null; // If file not uploaded
                }
            }

            UserDocuments::create(array_merge(
                ['user_id' => $request->emp_id],
                $filePaths
            ));

            return response()->json([
                'status' => true,
                'message' => 'Documnets saved successfully',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error occurred while saving Address Info', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while saving Address Info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function disableUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        try {

            $user = User::findOrFail($request->user_id);
            $user->is_disable = true;
            $user->save();

            return response()->json(['message' => 'Employee credentials disabled successfully!']);
        } catch (\Exception $e) {
            Log::error('Failed to disable user', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to disable user.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls'
        ]);
        try {
            Excel::import(new UsersImport, $request->file('file'));
            return response()->json(['message' => 'Data imported successfully!']);
        } catch (\Exception $e) {
            Log::error('Excel Import Failed', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Failed to import data.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
