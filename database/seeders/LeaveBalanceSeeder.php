<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;

class LeaveBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = Carbon::now()->year;
        $users = User::all();
        $leaveTypes = LeaveType::all(); // fetch all leave types dynamically

        foreach ($users as $user) {
            foreach ($leaveTypes as $type) {
                // Skip the old half-day leave type (id = 4)
                if ($type->id === 4) {
                    continue;
                }

                // Set default allocation per leave type
                $total = match ($type->id) {
                    1 => 21,    // Paid Leave
                    2 => 365,   // Unpaid Leave (treated as "infinite")
                    3 => 0,     // Comp-off
                    5 => 182,   // Maternity Leave
                    default => 0,
                };

                LeaveBalance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    [
                        'total_allocated' => $total,
                        'used_days' => 0,
                        'remaining_days' => $total,
                        'carry_forward_days' => 0,
                    ]
                );
            }
        }
    }
}
