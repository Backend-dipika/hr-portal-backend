<?php

namespace App\Http\Controllers\user;


use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Address;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProfileController extends Controller
{

    public function sendRoles()
    {
        $roles = Designation::select('id', 'name','department_id')->get();
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
                        'address1' => $request->input('permanent_address2'),
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
                'user' => $user->load('address'),
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
}
