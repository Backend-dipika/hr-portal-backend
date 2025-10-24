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

    public function sendRoles()
    {
        $roles = Designation::select('id', 'name', 'department_id')->get();
        return response()->json(['roles' => $roles], 200);
    }
    public function sendDepartments()
    {
        $departments = Department::select('id', 'name')->get();
        return response()->json(['departments' => $departments], 200);
    }

    public function update(Request $request, $id)
    {
        Log::info('🟢 [Profile Update] Request received', [
            'user_id' => $id,
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();

        try {
            // ✅ Find the user
            Log::info('🔍 [Profile Update] Attempting to find user', ['user_id' => $id]);
            $user = User::findOrFail($id);
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
            if ($request->hasAny(['current_address1', 'current_city', 'current_state', 'current_pin'])) {
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
            Log::info('🎉 [Profile Update] Transaction committed successfully', ['user_id' => $id]);

            return response()->json([
                'message' => 'Profile updated successfully',
                // 'user' => $user->load('address'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [Profile Update] Failed to update profile', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProfilePicture(Request $request)
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
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function deleteProfilePicture(Request $request)
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

    public function getUserDocuments($id)
    {
        try {
            $user = User::with('document')->findOrFail($id);

            return response()->json([
                'status' => true,
                'documents' => $user->document, // Only return the document relation
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching documents for user ID ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error fetching documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function updateDocuments(Request $request)
    // {
    //     Log::info('update Documents Request:', $request->all());

    //     $validator = Validator::make($request->all(), [
    //         'user_id' => 'required|exists:users,id',
    //         'document_name' => 'required|string',
    //         'option' => 'required|in:update,delete',
    //         'file' => 'nullable|file',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $userDocument = UserDocuments::where('user_id', $request->user_id)
    //             ->where('document_name', $request->document_name)
    //             ->first();

    //         if (!$userDocument) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Document not found.'
    //             ], 404);
    //         }

    //         // if ($request->option === 'delete') {
    //         // Delete file from storage if exists
    //         if ($userDocument->file_path && Storage::disk('public')->exists(str_replace('storage/', '', $userDocument->file_path))) {
    //             Storage::disk('public')->delete(str_replace('storage/', '', $userDocument->file_path));
    //         }
    //         $userDocument->delete();

    //         // }

    //         if ($request->option === 'update') {
    //             $filePath = $userDocument->file_path; // Keep existing if no new file

    //             if ($request->hasFile('file')) {
    //                 $file = $request->file('file');
    //                 $path = "user/{$request->user_id}";
    //                 $filename = $file->getClientOriginalName();

    //                 // Delete old file if exists
    //                 if ($filePath && Storage::disk('public')->exists(str_replace('storage/', '', $filePath))) {
    //                     Storage::disk('public')->delete(str_replace('storage/', '', $filePath));
    //                 }

    //                 // Store new file
    //                 Storage::disk('public')->putFileAs($path, $file, $filename);
    //                 $filePath = "storage/{$path}/{$filename}";
    //             }

    //             // Update DB
    //             $userDocument->update([
    //                 'document_name' => $request->document_name,
    //                 'file_path' => $filePath,
    //             ]);
    //         }
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Document updated successfully.',
    //             'file_path' => $filePath
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error updating/deleting document: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong.'
    //         ], 500);
    //     }
    // }

    public function updateDocuments(Request $request)
    {
        Log::info('update Documents Request:', $request->all());

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'document_name' => 'required|in:adhar_card,pan_card,certificate,experience_letter,salary_slip',
            'option' => 'required|in:update,delete',
            'file' => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $documentField = $request->document_name; // e.g. 'adhar_card'

            $userDocument = UserDocuments::where('user_id', $request->user_id)->first();

            if (!$userDocument) {
                return response()->json([
                    'status' => false,
                    'message' => 'Document record not found for this user.'
                ], 404);
            }

            $existingFilePath = $userDocument->$documentField;

            // Handle delete
            if ($request->option === 'delete') {
                if ($existingFilePath && Storage::disk('public')->exists(str_replace('storage/', '', $existingFilePath))) {
                    Storage::disk('public')->delete(str_replace('storage/', '', $existingFilePath));
                }

                $userDocument->update([$documentField => null]);

                return response()->json([
                    'status' => true,
                    'message' => ucfirst(str_replace('_', ' ', $documentField)) . ' deleted successfully.',
                ]);
            }

            // Handle update
            if ($request->option === 'update') {
                $filePath = $existingFilePath;

                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $path = "user/{$request->user_id}";
                    $filename = $file->getClientOriginalName();

                    // Delete old file if exists
                    if ($existingFilePath && Storage::disk('public')->exists(str_replace('storage/', '', $existingFilePath))) {
                        Storage::disk('public')->delete(str_replace('storage/', '', $existingFilePath));
                    }

                    // Store new file
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
            }
        } catch (\Exception $e) {
            Log::error('Error updating/deleting document: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }
}
