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
                'name' => 'paid',
                'max_allowed_days' => 30,
                'requires_approval' => true,
            ],
            [
                'name' => 'unpaid',
                'max_allowed_days' => 0, // unlimited (could be treated as no cap)
                'requires_approval' => true,
            ],
            [
                'name' => 'compoff',
                'max_allowed_days' => 10,
                'requires_approval' => true,
            ],
            [
                'name' => 'halfday',
                'max_allowed_days' => 30,
                'requires_approval' => true,
            ],
                        [
                'name' => 'maternity',
                'max_allowed_days' => 180,
                'requires_approval' => true,
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
