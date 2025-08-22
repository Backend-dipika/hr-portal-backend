<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('employee_types')->insert([
            [
                'uuid' => Str::uuid(),
                'name' => 'Full-time',
                'max_minutes_perday' => 555,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'part-time',
                'max_minutes_perday' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ]

        ]);
    }
}
