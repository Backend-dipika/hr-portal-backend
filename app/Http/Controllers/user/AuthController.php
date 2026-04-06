<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\otp;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    /**
     * Check Authenticated User & Send OTP
     *
     * Verifies if a user exists with the given mobile number and sends an OTP.
     *
     * @group Authentication
     *
     * @bodyParam mobile string required User mobile number. Example: 9876543210
     *
     * @response 200 {
     *  "status": true,
     *  "message": "OTP sent successfully. Please enter the OTP."
     * }
     *
     * @response 404 {
     *  "message": "User Not Found"
     * }
     */
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
            return response()->json(['message' => 'User Not Found'], 404);
        }

        $smsSent = $this->sendSms($user);

        if (!$smsSent) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }

        return response()->json([
            'status'  => true,
            'message' => 'OTP sent successfully. Please enter the OTP.'
        ], 200);
    }

    public function sendSms($user)
    {
        $otp = rand(1000, 9999);
        // $message = "Welcome to SAMS Digital HRMS ! Your verification code is {$otp}. It will only be valid till 5 minutes. Do not share your OTP with anyone for security reasons - SAMS DIGITAL";
        // //now
        $message = "Welcome to SAMS Digital HRMS ! Your verification code is {$otp}. It will only be valid till 5 minutes. Do not share your OTP with anyone for security reasons - SAMS DIGITAL";

        // integrate SMS gateway here (e.g., Digimiles)
        try {
            otp::where('user_id', $user->id)->delete();
            otp::create([
                'phone_number' => $user->phone_no,
                'otp' => Hash::make($otp),
                'is_used' => false,
                'expires_at' => now()->addMinutes(5),
                'user_id' => $user->id,
            ]);
            Log::info("OTP for {$user->phone_no}: $otp");
            $apiUrl   = config('services.sms.api_url');
            $apiKey   = config('services.sms.api_key');
            $senderId = config('services.sms.sender_id');
            Log::info("Using SMS API URL: " . $apiUrl);
            Log::info("Using SMS API key: " . $apiKey);
            Log::info("Using SMS sender Id: " . $senderId);

            $response = Http::get($apiUrl, [
                'apikey' => $apiKey,
                'type'   => 'TRANS',
                'text'   => $message,
                'to'     => $user->phone_no,
                'sender' => $senderId
            ]);

            Log::info("SMS API response: " . $response);
            if (!$response->successful()) {
                Log::error("SMS API failed for user {$user->id}. Status: " . $response->status());
                return false;
            }

            Log::info("OTP sent successfully for user {$user->id}");
            return true;

            // return response()->json($response);
        } catch (Exception $e) {
            Log::info(" Error in sendOtp method" . $e->getMessage());
            return false;
        }
        return;
    }

    /**
     * Login User with OTP
     *
     * Authenticates user using mobile number and OTP and returns access & refresh tokens.
     *
     * @group Authentication
     *
     * @bodyParam mobile string required User mobile number. Example: 9876543210
     * @bodyParam otp string required 4-digit OTP. Example: 1234
     *
     * @response 200 {
     *  "message": "Logged in",
     *  "user": {
     *    "id": 1,
     *    "uuid": "abc-123",
     *    "name": "John",
     *    "role": 2
     *  },
     *  "tokens": {
     *    "access_token": "token_here",
     *    "refresh_token": "refresh_here",
     *    "token_type": "Bearer"
     *  }
     * }
     *
     * @response 401 {
     *  "message": "Invalid or expired OTP"
     * }
     */
    public function login(Request $request)
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
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord || !Hash::check($request->otp, $otpRecord->otp)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }
        $otpRecord->delete();

        // Token generation 
        $accessToken = JWTAuth::fromUser($user);
        $refreshPayload = JWTFactory::customClaims([
            'sub' => $user->id,
            'type' => 'refresh',
            'exp' => now()->addMinutes((int) config('jwt.refresh_ttl'))->timestamp
        ])->make([]);

        $refreshToken = JWTAuth::encode($refreshPayload)->get();
        Log::info("OTP verified for user: {$user}");


        // Decode refresh token to get expiry
        $decodedRefreshPayload = JWTAuth::setToken($refreshToken)->getPayload();
        $refreshExp = $decodedRefreshPayload->get('exp');

        // 🔹 Debug logs
        Log::info('JWT Debug after login', [
            'user_id'             => $user->id,
            'refresh_exp_unix'    => $refreshExp,
            'refresh_exp_human'   => \Carbon\Carbon::createFromTimestamp($refreshExp)->toDateTimeString(),
            'refresh_token_sample' => $refreshToken // only log sample, avoid full token leak
        ]);

        Log::info('JWT refresh ttl from config', ['refresh_ttl' => config('jwt.refresh_ttl')]);

        return response()->json([
            'message' => 'Logged in',
            'user'    => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->first_name,
                'role' => $user->role_id
            ],
            'tokens'  => [                        // ← Mobile apps read from here
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
            ]
        ], 200)
            ->cookie('access_token',  $accessToken, 15,    '/', null, false, true, false, 'None')
            ->cookie('refresh_token', $refreshToken, 20160, '/', null, false, true, false, 'None');
        // ->cookie('access_token',  $accessToken, 15,    '/', null, true, true, false)  // ← Web reads from here
        // ->cookie('refresh_token', $refreshToken, 20160, '/', null, true, true, false);
    }

    /**
     * Refresh Access Token
     *
     * Generates a new access token using a valid refresh token.
     *
     * @group Authentication
     *
     * @bodyParam refresh_token string optional Refresh token (for mobile clients)
     *
     * @response 200 {
     *  "message": "Token refreshed",
     *  "user": {
     *    "id": 1,
     *    "uuid": "abc-123",
     *    "name": "John",
     *    "role": 2
     *  },
     *  "tokens": {
     *    "access_token": "new_access_token",
     *    "refresh_token": "new_refresh_token",
     *    "token_type": "Bearer"
     *  }
     * }
     *
     * @response 401 {
     *  "error": "Invalid refresh token"
     * }
     */
    public function refreshToken(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token') ?? $request->input('refresh_token');
        Log::info('Refresh token from frontend', ['refresh token value' => $refreshToken]);

        if (!$refreshToken) {
            return response()->json(['error' => 'No refresh token found'], 400);
            Log::info('No refresh token found from frontend');
        }

        try {
            // 1. Decode the refresh token
            $payload = JWTAuth::setToken($refreshToken)->getPayload();

            // 2. Verify it is actually a refresh token not an access token
            if ($payload->get('type') !== 'refresh') {
                Log::warning('Non-refresh token used in refresh endpoint');
                return response()->json(['error' => 'Invalid token type'], 401);
            }

            // 2. Extract user ID from the payload (we stored it in 'sub')
            $userId = $payload->get('sub');
            $user   = User::where('id', $userId)
                ->where('is_disable', false)
                ->first();

            if (!$user) {
                return response()->json(['error' => 'User not found or disabled'], 401);
            }

            // 4. Blacklist OLD refresh token before issuing new one
            //    This prevents reuse of the same refresh token (rotation security)
            try {
                JWTAuth::setToken($refreshToken)->invalidate();
            } catch (TokenExpiredException $e) {
                // Already expired, safe to proceed
            } catch (JWTException $e) {
                Log::warning('Could not blacklist old refresh token for user: ' . $user->id);
            }

            // 4. Generate a **new access token**
            $newAccessToken = JWTAuth::fromUser($user);

            // 5. Generate a **new refresh token** (rotating refresh)
            $newRefreshPayload = JWTFactory::customClaims([
                'sub' => $user->id,
                'type' => 'refresh',
                'exp' => now()->addMinutes((int) config('jwt.refresh_ttl'))->timestamp
            ])->make([]);

            $newRefreshToken = JWTAuth::encode($newRefreshPayload)->get();

            Log::info('Token refreshed for user: ' . $user->id);

            // 7. Build response
            $response = response()->json([
                'message' => 'Token refreshed',
                'user'    => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->first_name,
                    'role' => $user->role_id
                ],
                'tokens'  => [
                    'access_token'  => $newAccessToken,
                    'refresh_token' => $newRefreshToken,
                    'token_type'    => 'Bearer',
                ]
            ], 200);

            // 8. Set cookies only for web clients
            if ($request->cookie('refresh_token')) {
                $response = $response
                    ->cookie('access_token',  $newAccessToken,  15,    '/', null, true, true, false)
                    ->cookie('refresh_token', $newRefreshToken, 20160, '/', null, true, true, false);
            }

            return $response;
        } catch (TokenExpiredException $e) {
            Log::info('Expired refresh token used for user attempt');
            return response()->json(['error' => 'Refresh token expired, please login again'], 401);
        } catch (JWTException $e) {
            Log::error('Refresh token failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid refresh token'], 401);
        }
    }

    /**
     * Logout User
     *
     * Logs out the user by invalidating both access and refresh tokens.
     * 
     * This endpoint supports both:
     * - Web clients (via cookies: access_token, refresh_token)
     * - Mobile clients (via Authorization header and request body)
     *
     * @group Authentication
     *
     * @header Authorization Bearer {access_token} Example: Bearer eyJ0eXAiOiJKV1QiLCJh...
     *
     * @bodyParam refresh_token string optional Refresh token (required for mobile clients). Example: eyJ0eXAiOiJKV1QiLCJh...
     *
     * @response 200 {
     *  "message": "Logged out successfully"
     * }
     *
     * @response 500 {
     *  "message": "Failed to log out"
     * }
     */
    public function logout(Request $request)
    {
        try {
            // Support both cookie (web) and Authorization header (mobile)
            $accessToken  = $request->cookie('access_token')
                ?? $request->bearerToken();
            $refreshToken = $request->cookie('refresh_token')
                ?? $request->input('refresh_token');

            // Invalidate access token
            if ($accessToken) {
                try {
                    JWTAuth::setToken($accessToken)->invalidate();
                } catch (TokenExpiredException $e) {
                    // Already expired safe to ignore, proceed with logout
                    //If error come here then it means token is already expired and we can ignore this error and proceed with logout process
                } catch (JWTException $e) {
                    Log::warning('Access token invalidation failed for user: ' . optional($request->user())->id);
                }
            }

            // Invalidate refresh token
            if ($refreshToken) {
                try {
                    JWTAuth::setToken($refreshToken)->invalidate();
                } catch (TokenExpiredException $e) {
                    // Already expired — safe to ignore
                } catch (JWTException $e) {
                    Log::warning('Refresh token invalidation failed for user: ' . optional($request->user())->id);
                }
            }

            $response = response()->json(['message' => 'Logged out successfully'], 200);

            // Clear cookies only for web clients
            if ($request->cookie('access_token') || $request->cookie('refresh_token')) {
                $response = $response
                    ->cookie('access_token',  '', -1, '/', null, true, true, false)
                    ->cookie('refresh_token', '', -1, '/', null, true, true, false);
            }

            return $response;
        } catch (JWTException $e) {
            Log::error('Logout failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to log out'], 500);
        }
    }

    public function sendUserDetails(Request $request)
    {
        try {
            Log::info('inside get user info');
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'id' => $user->id,
                'role' => $user->role_id,
                'uuid' => $user->uuid,
            ], 200);
        } catch (\Exception $e) {
            // Log the error with stack trace
            Log::error('Error in sendUserDetails: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'error' => 'Something went wrong while fetching user details'
            ], 500);
        }
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
