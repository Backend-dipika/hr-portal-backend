<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            [
                'name' => 'Paid',
                'type' => 'Regular',
                'code' => 'PL',
                'max_allowed_days' => 30,
                'is_paid' => true,
            ],

            [
                'name' => 'Compoff',
                'type' => 'compensatory off',
                'code' => 'CF',
                'max_allowed_days' => 10,
                'is_paid' => true,
            ],
            [
                'name' => 'Unpaid',
                'type' => 'unpaid leave',
                'code' => '',
                'max_allowed_days' => 0, // unlimited (could be treated as no cap)
                'is_paid' => false,
            ],
            [
                'name' => 'Half Day',
                'type' => 'half day leave',
                'code' => '',
                'max_allowed_days' => 30,
                'is_paid' => true,
            ],
            [
                'name' => 'Maternity',
                'type' => 'maternity leave',
                'code' => '',
                'max_allowed_days' => 180,
                'is_paid' => true,
            ],


        ];

        foreach ($leaveTypes as $type) {
            LeaveType::updateOrCreate(
                ['name' => $type['name']], // prevent duplicates
                $type
            );
        }
    }
}
