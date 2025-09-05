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
        //Create the user
        DB::table('users')->insert(
            [

                // CEO (top-level user)
                [
                    'uuid' => Str::uuid(),
                    'salutation' => 'Mr.',
                    'first_name' => 'Michael',
                    'middle_name' => null,
                    'last_name' => 'Scott',
                    'employee_id' => 'EMP001',
                    'personal_email' => 'michael.personal@example.com',
                    'office_email' => 'michael.scott@company.com',
                    'phone_no' => '9999999901',
                    'alt_phone_no' => null,
                    'role_id' => 1, // Assuming role 1 is CEO or top-level
                    'department_id' => 1,
                    'designation_id' => 1,
                    'date_of_joining' => '2005-01-01',
                    'date_of_birth' => '1964-03-15',
                    'marital_status' => 'Married',
                    'about' => 'The World\'s Best Boss.',
                    'current_location' => 'Scranton, PA',
                    'blood_grp' => 'B-',
                    'specially_abled' => 0,
                    'employee_type_id' => 1,
                    'reporting_manager_id' => null,
                    'reporting_TL_id' => null,
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                // Manager
                [
                    'uuid' => Str::uuid(),
                    'salutation' => 'Ms.',
                    'first_name' => 'Pamela',
                    'middle_name' => 'Morgan',
                    'last_name' => 'Beesly',
                    'employee_id' => 'EMP002',
                    'personal_email' => 'pamela.personal@example.com',
                    'office_email' => 'pamela.beesly@company.com',
                    'phone_no' => '9999999902',
                    'alt_phone_no' => null,
                    'role_id' => 2, // Assuming role 2 is Manager
                    'department_id' => 1,
                    'designation_id' => 2,
                    'date_of_joining' => '2005-05-05',
                    'date_of_birth' => '1979-03-25',
                    'marital_status' => 'Married',
                    'about' => 'Office Administrator and Salesperson.',
                    'current_location' => 'Scranton, PA',
                    'blood_grp' => 'A+',
                    'specially_abled' => 0,
                    'employee_type_id' => 1,
                    'reporting_manager_id' => null, // Reports to Michael Scott
                    'reporting_TL_id' => null, // Reports to Michael Scott
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                // Regular Employee
                [
                    'uuid' => Str::uuid(),
                    'salutation' => 'Mr.',
                    'first_name' => 'Jim',
                    'middle_name' => null,
                    'last_name' => 'Halpert',
                    'employee_id' => 'EMP003',
                    'personal_email' => 'jim.personal@example.com',
                    'office_email' => 'jim.halpert@company.com',
                    'phone_no' => '9999999903',
                    'alt_phone_no' => null,
                    'role_id' => 3, // Assuming role 3 is Employee
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
                    'reporting_manager_id' => 2, // Reports to Pamela Beesly
                    'reporting_TL_id' => 2, // Reports to Pamela Beesly
                    'sepration_status' => 'resigned',
                    'probation_end_date' => '2025-12-05',
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'uuid' => Str::uuid(),
                    'salutation' => 'Mr.',
                    'first_name' => 'John',
                    'middle_name' => 'A',
                    'last_name' => 'Doe',
                    'employee_id' => '001',
                    'personal_email' => 'john.personal@example.com',
                    'office_email' => 'john.doe@company.com',
                    'phone_no' => '9999999999',
                    'alt_phone_no' => '8888888888',
                    'role_id' => 1,
                    'department_id' => 3,
                    'designation_id' => 1,
                    'date_of_joining' => '2022-01-01',
                    'date_of_birth' => '1990-01-01',
                    'marital_status' => 'Single',
                    'about' => 'A dedicated employee.',
                    'current_location' => 'New York',
                    'blood_grp' => 'O+',
                    'specially_abled' => 'No',
                    'employee_type_id' => 1,
                    'reporting_manager_id' => null,
                    'reporting_TL_id' => null,
                    'sepration_status' => 'reversed',
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],


            ]
        );
    }
}
