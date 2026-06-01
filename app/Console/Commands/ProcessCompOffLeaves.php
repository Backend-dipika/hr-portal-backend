<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProcessedAttendance;
use App\Models\LeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCompOffLeaves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-comp-off-leaves';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentYear = Carbon::now()->year;

        $weekendWorkers = ProcessedAttendance::select(
            'user_id',
            DB::raw('COUNT(DISTINCT attendance_date) as total_holiday_worked')
        )
            ->whereYear('attendance_date', $currentYear)
            ->whereRaw("
            EXTRACT(DOW FROM attendance_date) IN (0,6)
        ")
            ->groupBy('user_id')
            ->get();

        if ($weekendWorkers->isEmpty()) {

            $this->info('No weekend workers found.');

            return;
        }

        foreach ($weekendWorkers as $worker) {

            $user = User::where('office_id', $worker->user_id)->first();

            if (!$user) {

                $this->warn("No user found for office_id {$worker->user_id}");

                continue;
            }

            $totalWorkedHolidays = $worker->total_holiday_worked;

            Log::info('Comp off updated', [
                'user_id' => $user->id,
                'total_holiday_worked' => $totalWorkedHolidays
            ]);

            $this->info("Comp off updated for User {$user->id}");

            $leaveBalance = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', 2)
                ->where('year', $currentYear)
                ->first();

            if ($leaveBalance) {

                $leaveBalance->total_allocated = $totalWorkedHolidays;

                $leaveBalance->remaining_days =
                    $totalWorkedHolidays - $leaveBalance->used_days;

                $leaveBalance->save();

                $this->info("Updated leave balance for User {$user->id}");
            }
        }

        $this->info('Comp Off processing completed.');
    }
}
