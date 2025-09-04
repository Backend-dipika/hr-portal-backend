<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AuthController extends Controller
{
    public function checkAuthenticatedUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = User::where('phone_no', $request->mobile)
            ->where('is_disable', false)->first();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 404);
        }
        $this->sendSms($user);
        return response()->json(['message' => 'Verified Successfully, Enter otp'], 200);
    }
    public function sendSms($user)
    {
        $otp = rand(1000, 9999);
        otp::where('user_id', $user->id)->delete();
        otp::create([
            'phone_number' => $user->phone_no,
            'otp' => $otp,
            'is_used' => false,
            'expires_at' => now()->addMinutes(5),
            'user_id' => $user->id,
        ]);
        Log::info("OTP for {$user->phoneNo}: $otp");
        // integrate SMS gateway here (e.g., Twilio)
        return;
    }
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
            'otp' => 'required|string|size:4'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422); // 422 Unprocessable Entity
        }
        $user = User::where('phone_no', $request->mobile)
            ->where('is_disable', false)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $otpRecord = Otp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }
        $otpRecord->delete();

        // Token generation 
        $accessToken = JWTAuth::fromUser($user);
        $refreshPayload = JWTFactory::customClaims([
            'sub' => $user->id,
            'type' => 'refresh',
        ])->make([
            'exp' => now()->addMinutes((int) config('jwt.refresh_ttl'))->timestamp
        ]);

        $refreshToken = JWTAuth::encode($refreshPayload)->get();
        Log::info("OTP verified for user: {$user}");
        // return response()->json(['message' => 'Logged in', 'user' => $user], 200)
        //     ->cookie('access_token', $accessToken, 15, null, null, true, true) // 15 minutes
        //     ->cookie('refresh_token', $refreshToken, 10080, null, null, true, true); // 7 days
        return response()->json(['message' => 'Logged in', 'user' => $user], 200)
            ->cookie(
                'access_token',
                $accessToken,
                15,         // expiry in minutes
                '/',
                null,
                true,       // Secure
                true,       // HttpOnly
                false,      // Raw
                'None'      // <-- SameSite=None
            )
            ->cookie(
                'refresh_token',
                $refreshToken,
                10080,      // expiry in minutes (7 days)
                '/',
                null,
                true,       // Secure
                true,       // HttpOnly
                false,
                'None'      // <-- SameSite=None
            );
    }

    public function refreshToken(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'No refresh token found'], 400);
        }

        try {
            // 1. Decode the refresh token
            $payload = JWTAuth::setToken($refreshToken)->getPayload();

            // 2. Extract user ID from the payload (we stored it in 'sub')
            $userId = $payload->get('sub');

            // 3. Fetch user from database
            $user = User::findOrFail($userId);

            // 4. Generate a **new access token**
            $newAccessToken = JWTAuth::fromUser($user);

            // 5. Generate a **new refresh token** (rotating refresh)
            $refreshPayload = JWTFactory::customClaims([
                'sub'  => $user->id,
                'type' => 'refresh',
            ])->make([
                'exp' => now()->addMinutes((int) config('jwt.refresh_ttl'))->timestamp
            ]);

            $newRefreshToken = JWTAuth::encode($refreshPayload)->get();

            // 6. Send both tokens back in cookies
            // return response()->json(['message' => 'Token refreshed'])
            //     ->cookie('access_token', $newAccessToken, 15, '/', null, true, true)  // path '/' for all routes
            //     ->cookie('refresh_token', $newRefreshToken, 10080, '/', null, true, true);
            return response()->json(['message' => 'Logged in', 'user' => $user], 200)
                ->cookie(
                    'access_token',
                    $newAccessToken,
                    15,         // expiry in minutes
                    '/',
                    null,
                    true,       // Secure
                    true,       // HttpOnly
                    false,      // Raw
                    'None'      // <-- SameSite=None
                )
                ->cookie(
                    'refresh_token',
                    $newRefreshToken,
                    10080,      // expiry in minutes (7 days)
                    '/',
                    null,
                    true,       // Secure
                    true,       // HttpOnly
                    false,
                    'None'      // <-- SameSite=None
                );
        } catch (\Exception $e) {
            Log::error('Refresh token failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid or expired refresh token'], 401);
        }
    }

    // public function refreshToken(Request $request)
    // {
    //     $refreshToken = $request->cookie('refresh_token'); // Get refresh token from cookie

    //     try {
    //         // Load the refresh token
    //         JWTAuth::setToken($refreshToken);

    //         // Generate a new access token
    //         $newAccessToken = JWTAuth::refresh();

    //         // Optional: get the user for any further operations
    //         // $user = JWTAuth::setToken($newAccessToken)->toUser();

    //         // Return new access token in cookie
    //         return response()->json(['data' => 'Token refreshed'])
    //             ->cookie('access_token', $newAccessToken, 15, null, null, true, true);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Invalid refresh token'], 500);
    //     }
    // }

    public function logout(Request $request)
    {
        try {
            $token = $request->cookie('access_token');
            //   JWTAuth::invalidate(JWTAuth::getToken());
            if ($token) {
                JWTAuth::setToken($token)->invalidate();
            }
            return response()->json(['message' => 'Logged out successfully'], 200)
                ->cookie('access_token', '', -1)
                ->cookie('refresh_token', '', -1);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Failed to log out'], 500);
        }
    }


    public function sendUserDetails(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'role' => $user->role_id
        ], 200);
    }
}


// used tymon/jwt-auth for implementing authentication mechanism
// use Tymon\JWTAuth\Facades\JWTAuth;
// use Tymon\JWTAuth\Facades\JWTFactory;
//   publish file config jwt.php where all the expire of access and refres token anr mention 

//   in user model added 
//   public function getJWTIdentifier()
//     {
//         return $this->getKey(); //returns the primary key (usually id) of the user.
//     }

//     public function getJWTCustomClaims()
//     {
//         return []; //This method lets you add custom data (claims) to the JWT payload.
//     } 
// in config/auth.php
//   'guards' => [
       
//         'api' => [
//             'driver' => 'jwt', //Use the api guard for authentication here.(middleware('auth:api'))
//             'provider' => 'users',
//         ],
//     ],
//  need to check if  this authentication works for only routes in api.php or 
//  if works properly after applying auth:api middleware in web.php routes as well.
//also checking for refresh mechanism 
