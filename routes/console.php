<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



Schedule::command('leave:process-yearly')
    ->dailyAt('08:00'); 

Schedule::command('app:process-yearly-leaves')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        // 🧩 Debug logs (commented out now — enable if needed for troubleshooting)
        /*
        $logPath = storage_path('logs/yearly_leaves.log');
        $debugInfo = "🔍 Running yearly-leave scheduler at " . now() . PHP_EOL .
                     "Current working dir: " . getcwd() . PHP_EOL .
                     "APP_ENV=" . env('APP_ENV') . PHP_EOL .
                     "DB_CONNECTION=" . env('DB_CONNECTION') . PHP_EOL .
                     str_repeat('-', 50) . PHP_EOL;
        file_put_contents($logPath, $debugInfo, FILE_APPEND);
        */
    })
    ->appendOutputTo(storage_path('logs/yearly_leaves.log'));
