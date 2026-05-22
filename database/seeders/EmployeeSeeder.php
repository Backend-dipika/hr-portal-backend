<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
           DB::table('employees')->insert([

            [
                'user_id' => '101',
                'employee_name' => 'Shivanand Bagwe'
            ],
            [
                'user_id' => '110',
                'employee_name' => 'Simran Mukadam'
            ],
            [
                'user_id' => '112',
                'employee_name' => 'Tanuj Dulam'
            ],
            [
                'user_id' => '115',
                'employee_name' => 'Shivani'
            ],
            [
                'user_id' => '116',
                'employee_name' => 'chaitrali'
            ],
            [
                'user_id' => '118',
                'employee_name' => 'Unazia'
            ],
            [
                'user_id' => '119',
                'employee_name' => 'Sneha'
            ],
            [
                'user_id' => '120',
                'employee_name' => 'Dipika Epili'
            ],
            [
                'user_id' => '122',
                'employee_name' => 'Saurabh'
            ],
            [
                'user_id' => '126',
                'employee_name' => 'Guruprasad'
            ],
            [
                'user_id' => '127',
                'employee_name' => 'Mugdha'
            ],
            [
                'user_id' => '129',
                'employee_name' => 'Tanya'
            ],
            [
                'user_id' => '130',
                'employee_name' => 'Manoj'
            ],
            [
                'user_id' => '131',
                'employee_name' => 'Utsav'
            ],
            [
                'user_id' => '132',
                'employee_name' => 'Prathamesh'
            ]

        ]);
    
    }
}
