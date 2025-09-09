<?php

namespace App\Imports;

use App\Models\Address;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // return new User([
        $user = User::create([
            'uuid'          => Str::uuid(),
            'salutation'    => $row['salutation'] ?? null,
            'first_name'    => $row['first_name'] ?? null,
            'middle_name'   => $row['middle_name'],
            'last_name'     => $row['last_name'],
            'gender'        => $row['gender'],
            'office_id'   => $row['office_id'],
            'personal_email' => $row['personal_email'],
            'office_email'  => $row['office_email'],
            'phone_no'      => $row['phone_no'],
            'alt_phone_no'    => $row['alt_phone_no'],
            'role_id'    => 3,
            'department_id'   => $row['department_id'],
            'designation_id'     => $row['designation_id'],
            'date_of_joining'        => $row['date_of_joining'],
            'probation_end_date'   => Carbon::parse($row['date_of_joining'])->addMonths(3),
            'date_of_birth' => $row['date_of_birth'],
            'marital_status'  => $row['marital_status'] ?? null,
            'about'      => $row['about'] ?? null,
            'current_location'   => $row['current_location'] ?? null,
            'blood_grp'     => $row['blood_grp'] ?? null,
            'specially_abled'        => $row['specially_abled'] ?? null,
            'employee_type_id'   => $row['employee_type_id'],
            'reporting_manager_id' => $row['reporting_manager_id'] ?? null,
            'is_disable'  => false,
            'about'      => $row['about'],
        ]);
        Address::create([
            'uuid'      => Str::uuid(),
            'user_id'   => $user->id,
            'type'      => 'Current', //OnAdhar,Current
            'address1'  => $row['current_address1'],
            'address2'  => $row['current_address2'] ?? null,
            'city'      => $row['current_city'],
            'state'     => $row['current_state'],
            'pincode'   => $row['current_pincode'],
            'country'    => $row['current_country'],
        ]);

        // Permanent Address
        if (!empty($row['same_as_current']) && $row['same_as_current'] == 'yes') {
            // Copy current address
            Address::create([
                'uuid'     => Str::uuid(),
                'user_id'  => $user->id,
                'type'     => 'permanent',
                'address1' => $row['current_address1'],
                'address2'  => $row['current_address2'] ?? null,
                'city'     => $row['current_city'],
                'state'    => $row['current_state'],
                'pincode'  => $row['current_pincode'],
                'country'    => $row['current_country'],
            ]);
        } else {
            // Use permanent columns
            Address::create([
                'uuid'     => Str::uuid(),
                'user_id'  => $user->id,
                'type'     => 'permanent',
                'address1' => $row['permanent_address1'],
                'address2' => $row['permanent_address2'] ?? null,
                'city'     => $row['permanent_city'],
                'state'    => $row['permanent_state'],
                'pincode'  => $row['permanent_pincode'],
                'country'    => $row['permanent_country'],
            ]);
        }

        return $user;
    }
}
