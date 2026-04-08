<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create users and capture their IDs
        $userIds = [];

        $users = [
            [
                'uuid' => Str::uuid(),
                'salutation' => 'Mr.',
                'first_name' => 'Shivanand',
                'middle_name' => null,
                'last_name' => 'Bagwe',
                'office_id' => 'EMP001',
                'personal_email' => 'shivanand@gmail.com',
                'office_email' => 'shivanand@samsdigital.in',
                'phone_no' => '9029148733',
                'alt_phone_no' => null,
                'role_id' => 1,
                'department_id' => 1,
                'designation_id' => 1,
                'date_of_joining' => '2024-09-29',
                'date_of_birth' => '1964-03-15',
                'marital_status' => 'Married',
                'about' => 'Regional Manager of Dunder Mifflin Scranton branch.',
                'current_location' => 'Scranton, PA',
                'blood_grp' => 'B-',
                'specially_abled' => 0,
                'employee_type_id' => 1,
                'reporting_manager_id' => null,
                'reporting_TL_id' => null,
                'sepration_status' => null,
                'probation_end_date' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'salutation' => 'Ms.',
                'first_name' => 'Simran',
                'middle_name' => '',
                'last_name' => 'Mukadam',
                'office_id' => 'EMP003',
                'personal_email' => 'simran@gmail.com',
                'office_email' => 'simran@samsdigital.in',
                'phone_no' => '8355825162',
                'alt_phone_no' => null,
                'role_id' => 2,
                'department_id' => 1,
                'designation_id' => 2,
                'date_of_joining' => '2005-05-05',
                'date_of_birth' => '1979-03-25',
                'marital_status' => 'Married',
                'about' => 'Office Administrator.',
                'current_location' => 'Scranton, PA',
                'blood_grp' => 'A+',
                'specially_abled' => 0,
                'employee_type_id' => 1,
                'reporting_manager_id' => null,
                'reporting_TL_id' => null,
                'sepration_status' => null,
                'probation_end_date' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'salutation' => 'Ms.',
                'first_name' => 'Dipika',
                'middle_name' => null,
                'last_name' => 'Halpert',
                'office_id' => 'EMP004',
                'personal_email' => 'dipika@gmail.com',
                'office_email' => 'dipika@samsdigital.in',
                'phone_no' => '8433679313',
                'alt_phone_no' => null,
                'role_id' => 3,
                'department_id' => 2,
                'designation_id' => 3,
                'date_of_joining' => '2005-05-05',
                'date_of_birth' => '1978-10-01',
                'marital_status' => 'Married',
                'about' => 'Sales Representative.',
                'current_location' => 'Scranton, PA',
                'blood_grp' => 'O+',
                'specially_abled' => 0,
                'employee_type_id' => 1,
                'reporting_manager_id' => 2,
                'reporting_TL_id' => 2,
                'sepration_status' => 'inactive',
                'probation_end_date' => '2025-12-05',
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'salutation' => 'Mr.',
                'first_name' => 'Prathamesh',
                'middle_name' => null,
                'last_name' => 'surname',
                'office_id' => 'EMP002',
                'personal_email' => 'prathamesh@gmail.com',
                'office_email' => 'prathamesh@samsdigital.in',
                'phone_no' => '9833516985', 
                'alt_phone_no' => null,
                'role_id' => 3,
                'department_id' => 1,
                'designation_id' => 1,
                'date_of_joining' => '2024-09-29',
                'date_of_birth' => '1964-03-15',
                'marital_status' => '',
                'about' => 'user.',
                'current_location' => 'Scranton, PA',
                'blood_grp' => 'B-',
                'specially_abled' => 0,
                'employee_type_id' => 1,
                'reporting_manager_id' => null,
                'reporting_TL_id' => null,
                'sepration_status' => null,
                'probation_end_date' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // ✅ Insert one by one to capture each ID
        foreach ($users as $user) {
            $id = DB::table('users')->insertGetId($user);
            $userIds[] = $id;
        }

        // ✅ Create a user_documents record for each user
        $userDocuments = array_map(fn($id) => [
            'user_id'           => $id,
            'adhar_card'        => null,
            'pan_card'          => null,
            'salary_slip'       => null,
            'experience_letter' => null,
            'certificate'       => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $userIds);

        DB::table('user_documents')->insert($userDocuments);
    }
}
