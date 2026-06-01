<?php

namespace App\Console\Commands;

use App\Http\Controllers\user\RegistrationController;
use App\Models\LeaveBalance;
use App\Models\LeaveYearEndAction;
use App\Models\User;
use App\Notifications\YearEndConfirmationNotification;
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
                ->where('year', $yearClosed)
                ->where('leave_type_id', 1)
                ->first();

            // Check if a LeaveBalance record for the NEW cycle already exists
            $newLeaveBalanceExists = LeaveBalance::where('user_id', $user->id)
                ->where('year', $yearStarting)
                ->where('leave_type_id', 1)
                ->exists();

            if ($newLeaveBalanceExists) {
                $this->warn("User ID: {$user->id} already has a LeaveBalance record for the starting year ({$yearStarting}). Skipping allocation.");
                continue; // Move to the next user
            }

            if (!$leaveBalance) {
                $this->warn("User ID: {$user->id} has no LeaveBalance record for the closed year ({$yearClosed}). Allocating new year's leave.");
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

                $registrationController = new \App\Http\Controllers\user\RegistrationController();
                $registrationController->addLeaveForNewUser($user->id);

                $toUser = User::find($user->id);
                if (!$toUser) {
                    throw new \Exception("User not found for ID {$user->id}");
                }
                $toUser->notify(new YearEndConfirmationNotification);

                $this->info("Allocated 21 days for the **STARTING YEAR** ({$yearStarting}).");
            } else {
                $registrationController = new \App\Http\Controllers\user\RegistrationController();
                $registrationController->addLeaveForNewUser($user->id);

                $this->line("User: {$user->name} had 0 remaining days for {$yearClosed}. Allocated 21 days for {$yearStarting}.");
            }

            // Step 5: Notify HR/employee (email/slack/notification)
            // You would typically call a notification or mail class here.
        }

        $this->comment("--------------------------------------------------");
        $this->info('Yearly leave processing completed successfully.');
    }
}
