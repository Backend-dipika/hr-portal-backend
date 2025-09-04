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
        DB::table('users')->insert([
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
            'department_id' => 1,
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
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
