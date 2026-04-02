<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            return [
                // 5 attempts per mobile number per 10 minutes
                Limit::perMinutes(10, 5)->by('mobile:' . $request->input('mobile')),

                // 20 attempts per IP per hour (catches bots cycling numbers)
                Limit::perHour(20)->by('ip:' . $request->ip()),
            ];
        });
        RateLimiter::for('otp-verify', function (Request $request) {
             // 5 attempts per mobile number per 10 minutes
            return Limit::perMinutes(10, 5)->by('mobile:' . $request->input('mobile'));
        });
    }
}
