<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Access token cookie', [
            'access_token' => $request->cookie('access_token'),
            'refresh_token' => $request->cookie('refresh_token'),
            'path'  => $request->path(),
            'ip'    => $request->ip(),
        ]);
        return $next($request);
    }
}
