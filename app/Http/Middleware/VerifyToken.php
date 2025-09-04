<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerifyToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accesssToken = $request->cookie('access_token');

        if (!$accesssToken) {
            Log::warning('JWT Middleware: No access_token cookie found');
            return response()->json(['error' => 'Access token missing'], 401);
        }

        try {
            // Log::info('JWT Middleware: Access token found', [
            //     'token' => $accesssToken
            // ]);

            // Set token manually
            $user = JWTAuth::setToken($accesssToken)->authenticate();
            Log::info('JWT Middleware: user', [
                'user' => $user
            ]);

            if (!$user) {
                Log::error('JWT Middleware: Token valid but no user returned');
                return response()->json(['error' => 'User not found'], 401);
            }

            // Bind user into request auth context
            Auth::setUser($user);

            Log::info('JWT Middleware: User authenticated', [
                'id' => $user->id,
            ]);
        } catch (TokenExpiredException $e) {
            Log::error('JWT Middleware: Token expired', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Token expired'], 401);
        } catch (TokenInvalidException $e) {
            Log::error('JWT Middleware: Token invalid', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Token invalid'], 401);
        } catch (JWTException $e) {
            Log::error('JWT Middleware: General JWT error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Token error'], 401);
        }

        return $next($request);
    }
}
