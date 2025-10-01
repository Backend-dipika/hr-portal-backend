<?php

namespace App\Console\Commands;

use App\Models\LeaveBalance;
use App\Models\LeaveYearEndAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessYearlyLeaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-yearly-leaves';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process yearly leave actions for users completing 1 year';


    public function handle()
    {
        $today = Carbon::today();

        // Use the current year to denote the leave cycle that is starting
        $yearStarting = $today->year;

        // Use the previous year (the year of the last cycle's start) to denote the cycle that just closed
        $yearClosed = $today->copy()->subYear()->year;

        $this->info("Starting yearly leave processing for anniversaries today: " . $today->toDateString());
        $this->info("The closed cycle is tagged as: " . $yearClosed);
        $this->info("The starting cycle is tagged as: " . $yearStarting);

        // Step 1: Find users whose joining anniversary is today (2nd, 3rd, 4th, etc.)
        // Compare only the month and day, ignoring the year.
        $users = User::whereMonth('date_of_joining', $today->month)
            ->whereDay('date_of_joining', $today->day)
            ->get();

        if ($users->isEmpty()) {
            $this->comment('No users found with a joining anniversary today.');
            return;
        }

        foreach ($users as $user) {
            $this->comment("--------------------------------------------------");
            $this->line("Processing user: {$user->name} (ID: {$user->id})");
            $this->line("Joined on: {$user->date_of_joining}");

            // Step 2: Retrieve the LeaveBalance record for the year that JUST CLOSED
            $leaveBalance = LeaveBalance::where('user_id', $user->id)
                // Assuming the previous LeaveBalance record exists and is tagged with the year it closed
                ->where('year', $yearClosed)
                ->first();

            // Check if a LeaveBalance record for the NEW cycle already exists
            $newLeaveBalanceExists = LeaveBalance::where('user_id', $user->id)
                ->where('year', $yearStarting)
                ->exists();

            if ($newLeaveBalanceExists) {
                $this->warn("User ID: {$user->id} already has a LeaveBalance record for the starting year ({$yearStarting}). Skipping allocation.");
                continue; // Move to the next user
            }

            if (!$leaveBalance) {
                // This will happen for the first anniversary if no balance was created after joining, 
                // or if data is missing. We can still create the new year's balance.
                $this->warn("User ID: {$user->id} has no LeaveBalance record for the closed year ({$yearClosed}). Allocating new year's leave.");

                // Jump to creating the new year's balance
            } elseif ($leaveBalance->remaining_days > 0) {

                // Step 3a: Create pending action record for the **CLOSED YEAR**
                LeaveYearEndAction::create([
                    'user_id'     => $user->id,
                    'year'        => $yearClosed, // <-- FIXED: Action relates to the closed year
                    'action_type' => null, // to be chosen later (carry_forward or encashment)
                    'days'        => $leaveBalance->remaining_days,
                    'status'      => 'submitted',
                    'processed_on' => Carbon::now(),
                    'approver_id' => 1,
                ]);

                $this->info("Created PENDING action for **CLOSED YEAR** ({$yearClosed}) with {$leaveBalance->remaining_days} days.");

                // Step 3b: Optionally zero out the old balance to prevent re-use
                $leaveBalance->update(['remaining_days' => 0]); 

                // Step 4: Create the NEW LeaveBalance record for the **STARTING YEAR**
                LeaveBalance::create([
                    'user_id'            => $user->id,
                    'leave_type_id'      => 1, // Assuming '1' is the annual leave type
                    'year'               => $yearStarting, // <-- CORRECT: New balance for the starting year
                    'total_allocated'    => 21,
                    'used_days'          => 0,
                    'remaining_days'     => 21,
                    'carry_forward_days' => 0, 
                ]);

                $this->info("Allocated 21 days for the **STARTING YEAR** ({$yearStarting}).");
            } else {
                // Remaining days are 0 or less. Just create the new year's allocation.
                LeaveBalance::create([
                    'user_id'            => $user->id,
                    'leave_type_id'      => 1,
                    'year'               => $yearStarting, // <-- CORRECT: New balance for the starting year
                    'total_allocated'    => 21,
                    'used_days'          => 0,
                    'remaining_days'     => 21,
                    'carry_forward_days' => 0,
                ]);

                $this->line("User: {$user->name} had 0 remaining days for {$yearClosed}. Allocated 21 days for {$yearStarting}.");
            }

            // Step 5: Notify HR/employee (email/slack/notification)
            // You would typically call a notification or mail class here.
        }

        $this->comment("--------------------------------------------------");
        $this->info('Yearly leave processing completed successfully.');
    }
}
