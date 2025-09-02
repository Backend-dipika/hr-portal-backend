<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // ✅ Find the user
            $user = User::findOrFail($id);

            // ✅ Update user fields (only those present)
            $user->update($request->only([
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
            ]));

            // ✅ Handle Current Address
            if ($request->hasAny(['current_address1', 'current_city', 'current_state', 'current_pin'])) {
                $user->address()->updateOrCreate(
                    ['type' => 'current'],
                    [
                        'address1' => $request->input('current_address1'),
                        'city'     => $request->input('current_city'),
                        'state'    => $request->input('current_state'),
                        'pincode'  => $request->input('current_pin'),
                        'country'  => $request->input('current_country', 'India'),
                    ]
                );
            }

            // ✅ Handle Permanent Address
            if ($request->hasAny(['permanent_address1', 'permanent_city', 'permanent_state', 'permanent_pin'])) {
                $user->address()->updateOrCreate(
                    ['type' => 'permanent'],
                    [
                        'address1' => $request->input('permanent_address1'),
                        'city'     => $request->input('permanent_city'),
                        'state'    => $request->input('permanent_state'),
                        'pincode'  => $request->input('permanent_pin'),
                        'country'  => $request->input('permanent_country', 'India'),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->load('address'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
