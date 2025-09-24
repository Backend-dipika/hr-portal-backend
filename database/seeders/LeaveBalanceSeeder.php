<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LeaveBalance;
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

        foreach ($users as $user) {
            LeaveBalance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'leave_type_id' => 1, // Assuming 1 is "Annual Leave"
                    'year' => $year,
                ],
                [
                    'total_allocated' => 21,
                    'used_days' => 0,
                    'remaining_days' => 21,
                    'carry_forward_days' => 0,
                ]
            );
        }
    }
}
