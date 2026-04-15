<?php

namespace App\Http\Controllers\user;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Address;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\UserDocuments;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{

    /**
     * Get Roles (Designations)
     *
     * Fetch all roles along with their department mapping.
     *
     * @group Employee
     *
     * @response 200 {
     *   "roles": [
     *     {
     *       "id": 1,
     *       "name": "Backend Developer",
     *       "department_id": 2
     *     }
     *   ]
     * }
     */
    public function sendRoles()
    {
        $roles = Designation::select('id', 'name', 'department_id')->get();
        return response()->json(['roles' => $roles], 200);
    }

    /**
     * Get Departments
     *
     * Fetch all departments.
     *
     * @group Employee
     *
     * @response 200 {
     *   "departments": [
     *     {
     *       "id": 1,
     *       "name": "IT"
     *     }
     *   ]
     * }
     */
    public function sendDepartments()
    {
        $departments = Department::select('id', 'name')->get();
        return response()->json(['departments' => $departments], 200);
    }

    /**
     * Update User Profile
     *
     * Update basic profile information and address details of a user using UUID.
     * This is a partial update API (PATCH), so only provided fields will be updated.
     *
     * @group Profile
     *
     * @urlParam uuid string required User UUID. Example: be762142-95f0-45a6-aa31-023e9a8fe1d0
     *
     * @bodyParam first_name string optional First name of the user. Example: John
     * @bodyParam last_name string optional Last name of the user. Example: Doe
     * @bodyParam gender string optional Gender. Example: male
     * @bodyParam date_of_birth date optional Date of birth. Example: 1995-08-15
     * @bodyParam marital_status string optional Marital status. Example: single
     * @bodyParam blood_grp string optional Blood group. Example: O+
     * @bodyParam specially_abled boolean optional Specially abled status. Example: false
     * @bodyParam personal_email string optional Personal email. Example: john@gmail.com
     * @bodyParam alt_phone_no string optional Alternate phone number. Example: 9876543210
     * @bodyParam about string optional About user. Example: Software Engineer
     *
     * @bodyParam current_address1 string optional Current address line 1.
     * @bodyParam current_address2 string optional Current address line 2.
     * @bodyParam current_city string optional Current city. Example: Mumbai
     * @bodyParam current_state string optional Current state. Example: Maharashtra
     * @bodyParam current_pincode string optional Current pincode. Example: 400001
     * @bodyParam current_country string optional Current country. Default: India
     *
     * @bodyParam permanent_address1 string optional Permanent address line 1.
     * @bodyParam permanent_address2 string optional Permanent address line 2.
     * @bodyParam permanent_city string optional Permanent city. Example: Pune
     * @bodyParam permanent_state string optional Permanent state. Example: Maharashtra
     * @bodyParam permanent_pincode string optional Permanent pincode. Example: 411001
     * @bodyParam permanent_country string optional Permanent country. Default: India
     *
     * @response 200 {
     *   "message": "Profile updated successfully"
     * }
     *
     * @response 404 {
     *   "message": "User not found"
     * }
     *
     * @response 500 {
     *   "message": "Failed to update profile"
     * }
     */
    public function update(Request $request, $uuid)  //to update profile details--personal tab
    {
        Log::info('🟢 [Profile Update] Request received', [
            'user_id' => $uuid,
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();

        try {
            // ✅ Find the user
            Log::info('🔍 [Profile Update] Attempting to find user', ['user_id' => $uuid]);
            $user = User::where('uuid', $uuid)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            Log::info('✅ [Profile Update] User found', ['user' => $user->toArray()]);

            // ✅ Update user fields
            $updateData = $request->only([
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'marital_status',
                'blood_grp',
                'specially_abled',
                'personal_email',
                'alt_phone_no',
                'about',
            ]);

            if (!empty($updateData)) {
                Log::info('✏️ [Profile Update] Updating user fields', ['update_data' => $updateData]);
                $user->update($updateData);
                Log::info('✅ [Profile Update] User fields updated successfully');
            } else {
                Log::info('ℹ️ [Profile Update] No user fields to update');
            }

            // ✅ Handle Current Address
            if ($request->hasAny(['current_address1', 'current_city', 'current_state', 'current_pincode'])) {
                Log::info('🏠 [Profile Update] Updating/Creating current address');
                $user->address()->updateOrCreate(
                    ['type' => 'current'],
                    [
                        'uuid'     => Str::uuid(),
                        'address1' => $request->input('current_address1'),
                        'address2' => $request->input('current_address2'),
                        'city'     => $request->input('current_city'),
                        'state'    => $request->input('current_state'),
                        'pincode'  => $request->input('current_pincode'),
                        'country'  => $request->input('current_country', 'India'),
                    ]
                );
                Log::info('✅ [Profile Update] Current address updated/created successfully');
            } else {
                Log::info('ℹ️ [Profile Update] No current address fields provided');
            }

            // ✅ Handle Permanent Address
            if ($request->hasAny(['permanent_address1', 'permanent_city', 'permanent_state', 'permanent_pin'])) {
                Log::info('🏡 [Profile Update] Updating/Creating permanent address');
                $user->address()->updateOrCreate(
                    ['type' => 'permanent'],
                    [
                        'uuid'     => Str::uuid(),
                        'address1' => $request->input('permanent_address1'),
                        'address2' => $request->input('permanent_address2'),
                        'city'     => $request->input('permanent_city'),
                        'state'    => $request->input('permanent_state'),
                        'pincode'  => $request->input('permanent_pincode'),
                        'country'  => $request->input('permanent_country', 'India'),
                    ]
                );
                Log::info('✅ [Profile Update] Permanent address updated/created successfully');
            } else {
                Log::info('ℹ️ [Profile Update] No permanent address fields provided');
            }

            DB::commit();
            Log::info('🎉 [Profile Update] Transaction committed successfully', ['user_id' => $uuid]);

            return response()->json([
                'message' => 'Profile updated successfully',
                // 'user' => $user->load('address'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [Profile Update] Failed to update profile', [
                'user_id' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update profile',
                // 'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload / Update Profile Picture
     *
     * Upload a new profile picture for a user. Replaces existing image if present.
     *
     * @group Employee
     *
     * @bodyParam user_id integer required User ID. Example: 1
     * @bodyParam profile_photo file required Image file (jpeg, png, jpg, gif, svg). Max size: 2MB.
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Profile Photo saved successfully",
     *   "file_path": "storage/user/1/profile/profile.jpg"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "profile_photo": ["The profile photo field is required."]
     *   }
     * }
     */

    public function updateProfilePicture(Request $request)   //to upload profile picture
    {
        Log::info('Employeement Request Data:', $request->all());
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
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

            // Delete existing profile picture if exists
            if ($user->profile_picture && Storage::disk('public')->exists(str_replace('storage/', '', $user->profile_picture))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $user->profile_picture));
            }

            // Upload new image
            $file = $request->file('profile_photo');
            $path = "user/{$request->user_id}/profile";
            $filename = $file->getClientOriginalName();

            Storage::disk('public')->putFileAs($path, $file, $filename);

            // Save new file path
            $file_path = "storage/{$path}/{$filename}";
            $user->update(['profile_picture' => $file_path]);


            return response()->json([
                'status' => true,
                'message' => 'Profile Photo saved successfully',
                'file_path' => $user->profile_picture
            ]);
        } catch (Exception $e) {
            Log::error('Error occurred while saving profile picture', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error occurred while saving profile picture',
                // 'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Profile Picture
     *
     * Deletes the existing profile picture of a user.
     *
     * @group Employee
     *
     * @bodyParam user_id integer required User ID. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Profile picture deleted successfully."
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No profile picture found to delete."
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "user_id": ["The user id field is required."]
     *   }
     * }
     */

    public function deleteProfilePicture(Request $request) //to delete profile picture
    {
        Log::info('Delete Profile Picture Request:', $request->all());

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
            $user = User::find($request->user_id);

            // Check and delete existing file
            if ($user->profile_picture && Storage::disk('public')->exists(str_replace('storage/', '', $user->profile_picture))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $user->profile_picture));
                $user->update(['profile_picture' => null]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No profile picture found to delete.',
                    'file_path' => $user->profile_picture
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile picture deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting profile picture: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while deleting profile picture.'
            ], 500);
        }
    }

    /**
     * Get User Documents
     *
     * Fetch all uploaded documents of a user.
     *
     * @group Employee Documents
     *
     * @urlParam user_uuid string required User UUID. Example: be762142-95f0-45a6-aa31-023e9a8fe1d0
     *
     * @response 200 {
     *   "status": true,
     *   "documents": {
     *     "adhar_card": "storage/user/1/adhar.pdf",
     *     "pan_card": "storage/user/1/pan.pdf",
     *     "certificate": null,
     *     "experience_letter": null,
     *     "salary_slip": "storage/user/1/salary.pdf"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "No query results for model"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error fetching documents"
     * }
     */
    public function getUserDocuments($user_uuid) //to get user documents
    {
        try {
            $user = User::with('document')->where('uuid', $user_uuid)->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $doc = $user->document;
            if (!$doc) {
                return response()->json([
                    'status' => false,
                    'message' => 'Documents not found for this user'
                ], 404);
            }

            $data = [
                'id' => $doc->id,
                'user_id' => $doc->user_id,
                'adhar_card' => $doc->adhar_card ?? '',
                'pan_card' => $doc->pan_card ?? '',
                'certificate' => $doc->certificate ?? '',
                'experience_letter' => $doc->experience_letter ?? '',
                'salary_slip' => $doc->salary_slip ?? '',
                'created_at' => $doc->created_at,
                'updated_at' => $doc->updated_at,
            ];

            return response()->json([
                'status' => true,
                'documents' => $data,
            ]);
            // return response()->json([
            //     'status' => true,
            //     'documents' => $user->document, // Only return the document relation
            // ]);
        } catch (Exception $e) {
            Log::error('Error fetching documents for user UUID ' . $user_uuid . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching documents',
                // 'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update Employee Document
     *
     * Update a specific document of a user.
     *
     * @group Employee Documents
     *
     * @urlParam user_uuid string required User UUID. Example: be762142-95f0-45a6-aa31-023e9a8fe1d0
     *
     * @bodyParam document_name string required Document type.
     * Must be one of: adhar_card, pan_card, certificate, experience_letter, salary_slip. Example: adhar_card
     * @bodyParam file file required Upload document file.
     *
     * @multipart
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Adhar card updated successfully.",
     *   "file_path": "storage/user/1/adhar.pdf"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "User not found"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Something went wrong."
     * }
     */
    public function updateDocument(Request $request, $user_uuid)
    {
        Log::info('Update Document Request:', $request->all());

        $validator = Validator::make($request->all(), [
            'document_name' => 'required|in:adhar_card,pan_card,certificate,experience_letter,salary_slip',
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $documentField = $request->document_name;

            $user = User::where('uuid', $user_uuid)->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $userDocument = UserDocuments::where('user_id', $user->id)->first();
            if (!$userDocument) {
                return response()->json([
                    'status' => false,
                    'message' => 'Document record not found for this user.'
                ], 404);
            }

            $existingFilePath = $userDocument->$documentField;
            $filePath = $existingFilePath;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = "user/{$user->id}";
                $filename = $file->getClientOriginalName();

                if ($existingFilePath && Storage::disk('public')->exists(str_replace('storage/', '', $existingFilePath))) {
                    Storage::disk('public')->delete(str_replace('storage/', '', $existingFilePath));
                }

                Storage::disk('public')->putFileAs($path, $file, $filename);
                $filePath = "storage/{$path}/{$filename}";
            }

            $userDocument->update([
                $documentField => $filePath
            ]);

            return response()->json([
                'status' => true,
                'message' => ucfirst(str_replace('_', ' ', $documentField)) . ' updated successfully.',
                'file_path' => $filePath
            ]);
        } catch (Exception $e) {
            Log::error('Error updating document: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }

    /**
     * Delete Employee Document
     *
     * Delete a specific document of a user.
     *
     * @group Employee Documents
     *
     * @urlParam user_uuid string required User UUID. Example: be762142-95f0-45a6-aa31-023e9a8fe1d0
     *
     * @bodyParam document_name string required Document type.
     * Must be one of: adhar_card, pan_card, certificate, experience_letter, salary_slip. Example: adhar_card
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Adhar card deleted successfully."
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Document record not found for this user."
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Validation failed"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Something went wrong."
     * }
     */
    public function deleteDocument(Request $request, $user_uuid)
    {
        Log::info('Delete Document Request:', $request->all());

        $validator = Validator::make($request->all(), [
            'document_name' => 'required|in:adhar_card,pan_card,certificate,experience_letter,salary_slip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $documentField = $request->document_name;

            $user = User::where('uuid', $user_uuid)->first();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $userDocument = UserDocuments::where('user_id', $user->id)->first();
            if (!$userDocument) {
                return response()->json([
                    'status' => false,
                    'message' => 'Document record not found for this user.'
                ], 404);
            }

            $existingFilePath = $userDocument->$documentField;

            if ($existingFilePath && Storage::disk('public')->exists(str_replace('storage/', '', $existingFilePath))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $existingFilePath));
            }

            $userDocument->update([
                $documentField => null
            ]);

            return response()->json([
                'status' => true,
                'message' => ucfirst(str_replace('_', ' ', $documentField)) . ' deleted successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting document: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }
}
