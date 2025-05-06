<?php

namespace App\Http\Controllers;

use App\Mail\CustomEmailVerification;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Services\ApiResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * @operationId User Registration
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Validate user inputs
            $fields = $request->validate([
                'username' => 'required|string|max:10|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'phone' => 'nullable|string|unique:users',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            // Create the user
            $user = User::create([
                'username' => $fields['username'],
                'email' => $fields['email'],
                'password' => Hash::make($fields['password']),
            ]);

            // Assign "user" role
            $user->assignRole('user');

            // Generate a custom email verification token
            $rawToken = Str::random(64); // Raw token for the verification link
            $hashedToken = Hash::make($rawToken); // Hashed token for secure storage
            $tokenExpiresAt = now()->addHour(); // Set expiration to 1 hour from now

            // Store the hashed token in the database
            DB::table('email_verification_tokens')->insert([
                'user_id' => $user->id,
                'token' => $hashedToken,
                'token_expires_at' => $tokenExpiresAt,
                'created_at' => now(),
            ]);

            // Send the verification email (pass only raw token)
            Mail::to($user->email)->queue(new CustomEmailVerification($rawToken));

            // Return a success response
            return apiResponse(true, 'User registered successfully! Check email for verification.',
                $user, 'user');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User login
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // ✅ Validate user inputs
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'password' => 'required',
            ]);

            // ✅ Find the user
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return apiResponse(false, 'Incorrect email or password', [], null);
            }


            // ✅ Check if the user's email is verified
            if (!$user->email_verified_at) {
                return apiResponse(false, 'Your email address is not verified. Please verify your email.', [], 403);
            }

            $sessionId = session()->getId();
            $ipAddress = $request->ip();
            $userAgent = $request->header('User-Agent');
            $lastActivity = Carbon::now()->timestamp;

            // ✅ Check if user has 2FA enabled
            if ($user->two_factor_confirmed_at) {
                $sessionData = [
                    'email' => $user->email,
                    '2fa_required' => true,
                    'session_id' => $sessionId,
                ];

                // Encrypt and set the cookie for 2FA verification
                $cookie = cookie(
                    '_2fa_session',
                    encrypt(json_encode($sessionData)),
                    15, // Expire in 15 minutes
                    '/',
                    null,
                    config('session.secure'),
                    config('session.http_only'),
                    false,
                    config('session.same_site')
                );

                return apiResponse(true, 'Please provide the OTP.', [], null)
                    ->withCookie($cookie);
            }

            // ✅ If no 2FA is enabled, generate a token and store session
            $token = $user->createToken($user->username);
            $expiresAt = Carbon::now()->addDays(7);

            // Update the latest token's expiration
            $latestToken = $user->tokens()->latest()->first();
            if ($latestToken) {
                $latestToken->update(['expires_at' => $expiresAt]);
            }

            // Store session
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_type' => $request->input('device_type'),
                'payload' => base64_encode(json_encode([
                    'token' => $token->plainTextToken,
                    'expires_at' => $expiresAt,
                ])),
                'last_activity' => $lastActivity,
            ]);

            // ✅ Return success response with token
            return apiResponse(true, 'Login successful', [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                ],
                'token' => [
                    'accessToken' => $latestToken,
                    'plainTextToken' => $token->plainTextToken,
                ]
            ], null, 200); // Set wrapper key to null

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Not authenticated', [], null, 401);
            }

            // Get the current access token
            $currentToken = $user->currentAccessToken();

            if (!$currentToken) {
                return apiResponse(false, 'No active session found', [], null);
            }

            $tokenId = $currentToken->id; // Get the token ID

            // Retrieve all sessions for the user
            $sessions = DB::table('sessions')->where('user_id', $user->id)->get();

            foreach ($sessions as $session) {
                $payload = json_decode(base64_decode($session->payload), true);

                if (isset($payload['token'])) {
                    // Extract the token ID from the session payload
                    $storedTokenParts = explode('|', $payload['token']);
                    $storedTokenId = $storedTokenParts[0] ?? null;

                    if ($storedTokenId == $tokenId) {
                        // Delete the session associated with the token
                        DB::table('sessions')->where('id', $session->id)->delete();
                        break;
                    }
                }
            }

            // Revoke the current token
            $currentToken->delete();

            return apiResponse(true, 'Logged out successfully.', [], null);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return apiResponse(false, 'An error occurred during logout',
                $e->getMessage(), 'errors');
        }
    }

    /**
     * @operationId User sessions
     */
    public function currentUserSessions(Request $request): JsonResponse
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'User not authenticated', [], null, 401);
            }

            // Retrieve all sessions associated with the current user
            $sessions = DB::table('sessions')->where('user_id', $user->id)->get();

            // Prepare sessions for response
            $formattedSessions = $sessions->map(function ($session) {
                $payload = json_decode(base64_decode($session->payload), true);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'device_type' => $session->device_type,
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toDateTimeString(),
                ];
            });

            // Return a success response
            return apiResponse(true, 'User sessions retrieved successfully.',
                $formattedSessions, 'sessions');

        } catch (\Exception $e) {
            // Handle unexpected errors
            return apiResponse(false, 'An error occurred while retrieving user sessions',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User gogle login redirect
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * @operationId User login with google
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        try {
            // Get Google access token from request body
            $googleToken = $request->input('token');

            if (!$googleToken) {
                return apiResponse(false, 'Google token is required.', [], null);
            }

            // Fetch user data from Google
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($googleToken);

            // Find existing user by email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Update Google ID and other details if the user exists
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'username' => $googleUser->getName() ?? $user->username, // Optional: Update name if needed
                ]);
            } else {
                // Create new user if no matching email is found
                $user = User::create([
                    'username' => $googleUser->getName() ?? explode('@', $googleUser->getEmail())[0], // Fallback to email prefix
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => bcrypt(uniqid()), // Random password for new users
                ]);
            }

            // Create a Sanctum token
            $token = $user->createToken($user->username);
            $expiresAt = Carbon::now()->addDays(7);

            $latestToken = $user->tokens()->latest()->first();
            if ($latestToken) {
                $latestToken->update(['expires_at' => $expiresAt]);
            }

            // Return success response
            return apiResponse(true, 'Google authentication successful.', [
                'user' => $user,
                'token' => [
                    'accessToken' => $latestToken,
                    'plainTextToken' => $token->plainTextToken,
                ],
            ], null);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return apiResponse(false, 'Authentication failed.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User email verification
     */
    public function verifyEmail(Request $request)
    {
        try {
            // Get the token from the query parameters
            $rawToken = $request->query('token');

            if (!$rawToken) {
                return apiResponse(false, 'Verification token is required', [], null);
            }

            // Find the verification token record where the token has not expired
            $record = DB::table('email_verification_tokens')
                ->where('token_expires_at', '>', now())
                ->first();

            // Check if the token exists
            if (!$record) {
                return apiResponse(false, 'Invalid or expired verification token', [], null);
            }

            // Verify the raw token against the hashed token in the database
            if (!Hash::check($rawToken, $record->token)) {
                return apiResponse(false, 'Invalid verification token', [], null);
            }

            // Find the user
            $user = User::find($record->user_id);

            if (!$user) {
                return apiResponse(false, 'User not found', [], null);
            }

            // Mark the user's email as verified
            $user->email_verified_at = now();
            $user->save();

            // Delete the token record after successful verification
            DB::table('email_verification_tokens')->where('id', $record->id)->delete();

            // Return a success response
            return apiResponse(true, 'Email successfully verified', [], null);

        } catch (\Exception $e) {
            // Handle any unexpected errors
            return apiResponse(false, 'An error occurred during email verification',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User resend email verification
     */
    public function resendVerificationEmail(Request $request)
    {
        try {
            // Validate the email input
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Find the user by email
            $user = User::where('email', $request->email)->first();

            // Check if the email is already verified
            if ($user->email_verified_at) {
                return apiResponse(false, 'This email is already verified.', [], null);
            }

            // Generate a new raw token and hash it
            $rawToken = Str::random(64);
            $hashedToken = Hash::make($rawToken);
            $tokenExpiresAt = now()->addHour(); // Token expires in 1 hour

            // Delete any existing tokens for the user
            DB::table('email_verification_tokens')->where('user_id', $user->id)->delete();

            // Insert the new token into the database
            DB::table('email_verification_tokens')->insert([
                'user_id' => $user->id,
                'token' => $hashedToken,
                'token_expires_at' => $tokenExpiresAt,
                'created_at' => now(),
            ]);

            // Send the verification email
            Mail::to($user->email)->queue(new CustomEmailVerification($rawToken));

            // Return a success response
            return apiResponse(true, 'Verification email resent successfully. Please check your email.', [], null);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User update user info
     */
    public function updateUserInfo(Request $request)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null, 401);
            }

            // Validate the request
            $validated = $request->validate([
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
                'current_password' => 'required|string',
            ]);

            // Verify the current password
            if (!Hash::check($request->current_password, $user->password)) {
                return apiResponse(false, 'Incorrect current password.', [], null, 401);
            }

            // Handle avatar upload with Spatie Media Library
            if ($request->hasFile('avatar')) {
                // Remove old avatar if exists
                $user->clearMediaCollection('avatars');

                // Upload new avatar and store it in the 'avatars' collection
                $user->addMedia($request->file('avatar'))
                    ->toMediaCollection('avatars');
            }

            // Save any other user changes (if applicable)
            $user->save();

            // Return success response with updated user info, including media
            return apiResponse(true, 'User information updated successfully.',
                $user->load('media'), // Load the media relationship
                'user',
                200
            );

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User update password
     */
    public function updateCurrentUserPassword(Request $request)
    {
        try {

            // Check if the user is authenticated using Sanctum guard
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null, 401);
            }
            // Validate the request
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed|different:current_password',
            ]);

            // Verify the current password before updating
            if (!Hash::check($request->current_password, $user->password)) {
                return apiResponse(false, 'Incorrect current password.', [], null, 403);
            }

            // Update the password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return apiResponse(true, 'Password updated successfully.', [], null, 200);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User send reset password instruction
     */
    public function sendResetPasswordInstruction(Request $request)
    {
        try {
            // ✅ Validate email
            $request->validate(['email' => 'required|email|exists:users,email']);

            $user = User::where('email', $request->email)->firstOrFail();

            // ✅ Generate a unique reset token
            $token = Str::random(64);

            // ✅ Store the token in the database
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => Carbon::now(),
                ]
            );

            // ✅ Send the reset email asynchronously
            Mail::to($user->email)->queue(new ResetPasswordMail($user, $token));

            return apiResponse(true, 'Password reset link sent successfully.', [], null);

        }  catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User reset password
     */
    public function resetPassword(Request $request)
    {
        try {
            // ✅ Validate the incoming request
            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:6|confirmed',
            ]);

            // ✅ Check if the reset token is valid
            $tokenData = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$tokenData || !Hash::check($request->token, $tokenData->token)) {
                return apiResponse(false, 'Invalid or expired token.', [], null);
            }

            // ✅ Find the user and reset their password
            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            // ✅ Delete the token after use
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return apiResponse(true, 'Password reset successfully.', [], null);
        }  catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId User information
     */
    public function currentUserInfo(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthenticated.', [], null);
        }
        return apiResponse(true, 'User information retrieved successfully!', $user->load('media'), 'user', 200);
    }

    /**
     * @operationId User enable 2FA
     */
    public function enableTwoFa(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthenticated.', [], null);
        }

        // Validate the current password
        $request->validate([
            'password' => 'required',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return apiResponse(true, 'Invalid password.', [], null);
        }

        // Check if 2FA is already confirmed
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            return apiResponse(true, '2FA already enabled.', [], null);
        }

        // Generate a new secret key and recovery codes
        $google2fa = new Google2FA();
        $secretKey = $google2fa->generateSecretKey();

        $user->two_factor_secret = encrypt($secretKey);
        $user->two_factor_recovery_codes = encrypt(json_encode([
            Str::random(10) . '-' . Str::random(10),
            Str::random(10) . '-' . Str::random(10),
        ]));
        $user->two_factor_confirmed_at = null; // Reset confirmation status
        $user->save();

        // Return the secret key and QR code URL
        return apiResponse(true, '2FA setup initiated successfully.', [
            'secret' => $secretKey,
            'qr_url' => "otpauth://totp/yoyda?secret={$secretKey}&issuer={$user->email}"
        ], 'two_fa', 200);
    }

    /**
     * @operationId User activate 2FA
     */
    public function activateTwoFa(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null);
            }

            $request->validate([
                'code' => 'required|numeric',
            ]);


            // Check if 2FA is already confirmed
            if ($user->two_factor_confirmed_at) {
                return apiResponse(false, '2FA is already activated.', [], null);
            }

            // Check if 2FA is enabled
            if (!$user->two_factor_secret) {
                return apiResponse(false, '2FA not enabled', [], null);
            }

            $google2fa = new Google2FA();

            // Verify the submitted code with the user's 2FA secret
            $isValid = $google2fa->verifyKey(decrypt($user->two_factor_secret), $request->code);

            if (!$isValid) {
                return apiResponse(false, 'Invalid code', [], null);
            }

            // Generate 8 recovery codes after successful verification
            $recoveryCodes = [];
            for ($i = 0; $i < 8; $i++) {
                $recoveryCodes[] = Str::random(10) . '-' . Str::random(10);
            }

            // Store the recovery codes and mark 2FA as confirmed
            $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
            $user->two_factor_confirmed_at = now(); // Mark 2FA as confirmed
            $user->save();

            return apiResponse(true, '2FA activated successfully',
                $recoveryCodes, 'recovery_codes', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'Failed to activate 2FA', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @operationId User show 2FA recovery codes
     */
    public function showRecoveryCodes(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null);
            }

            // Check if 2FA is enabled and recovery codes exist
            if (!$user->two_factor_secret) {
                return apiResponse(false, '2FA is not enabled', [], null);
            }

            if (!$user->two_factor_recovery_codes) {
                return apiResponse(false, 'No recovery codes found', [], null);
            }

            // Decrypt and return the recovery codes
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            return apiResponse(true, 'Recovery codes retrieved successfully',
                $recoveryCodes, 'recovery_codes', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'Failed to retrieve recovery codes',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User 2FA regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null);
            }

            // Check if 2FA is enabled
            if (!$user->two_factor_secret) {
                return apiResponse(false, '2FA is not enabled', [], null);
            }

            // Generate new recovery codes
            $newRecoveryCodes = [];
            for ($i = 0; $i < 8; $i++) {
                $newRecoveryCodes[] = Str::random(10) . '-' . Str::random(10);
            }

            // Save the new recovery codes in the database
            $user->two_factor_recovery_codes = encrypt(json_encode($newRecoveryCodes));
            $user->save();

            // Return the new recovery codes
            return apiResponse(true, 'Recovery codes regenerated successfully',
                $newRecoveryCodes, 'recovery_codes', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'Failed to regenerate recovery codes',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User login with 2FA
     */
    public function loginWithTwoFa(Request $request)
    {
        try {
            // ✅ Validate the 2FA code
            $request->validate([
                'two_factor_code' => 'required|numeric',
            ]);

            // ✅ Retrieve and validate the `_2fa_session` cookie
            $cookie = $request->cookie('_2fa_session');
            if (!$cookie) {
                return apiResponse(false, '2FA session cookie is missing or expired', [], null);
            }

            try {
                $sessionData = json_decode(Crypt::decrypt($cookie), true);
            } catch (\Exception $e) {
                return apiResponse(false, 'Invalid 2FA session cookie', [], null);
            }

            // Validate session state
            if (empty($sessionData['2fa_required']) || !$sessionData['2fa_required']) {
                return apiResponse(false, '2FA is not required for this session', [], null);
            }

            $user = User::where('email', $sessionData['email'])->first();

            if (!$user) {
                return apiResponse(false, 'User not found', [], null);
            }

            if (!$user->two_factor_secret) {
                return apiResponse(false, '2FA is not enabled for this user', [], null);
            }

            // ✅ Verify the 2FA code
            $google2fa = new Google2FA();
            $isValid = $google2fa->verifyKey(decrypt($user->two_factor_secret), $request->two_factor_code);

            if (!$isValid) {
                return apiResponse(false, 'Invalid 2FA code', [], null);
            }

            // ✅ If 2FA is correct, issue the access token
            $token = $user->createToken($user->username);
            $expiresAt = Carbon::now()->addDays(7);

            $latestToken = $user->tokens()->latest()->first();
            if ($latestToken) {
                $latestToken->update(['expires_at' => $expiresAt]);
            }

            // ✅ Store Session Manually
            DB::table('sessions')->insert([
                'id' => session()->getId(), // Generate a session ID
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'payload' => base64_encode(json_encode([
                    'token' => $token->plainTextToken,
                    'expires_at' => $expiresAt
                ])), // Store the token info securely
                'last_activity' => Carbon::now()->timestamp
            ]);

            // Clear the `_2fa_session` cookie after successful login
            return apiResponse(true, 'Login successful', [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                ],
                'token' => [
                    'accessToken' => $latestToken,
                    'plainTextToken' => $token->plainTextToken,
                ]
            ], null, 200)->withCookie(cookie()->forget('_2fa_session'));
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while verifying the OTP',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User login with 2FA recovery code
     */
    public function loginWithRecoveryCode(Request $request)
    {
        try {
            // ✅ Validate the recovery code
            $request->validate([
                'code' => 'required|string',
            ]);

            // ✅ Retrieve and validate the `_2fa_session` cookie
            $cookie = $request->cookie('_2fa_session');
            if (!$cookie) {
                return apiResponse(false, '2FA session cookie is missing or expired', [], null);
            }

            try {
                $sessionData = json_decode(Crypt::decrypt($cookie), true);
            } catch (\Exception $e) {
                return apiResponse(false, 'Invalid 2FA session cookie', [], null);
            }

            // Validate session state
            if (empty($sessionData['2fa_required']) || !$sessionData['2fa_required']) {
                return apiResponse(false, '2FA is not required for this session', [], null);
            }

            $user = User::where('email', $sessionData['email'])->first();

            if (!$user) {
                return apiResponse(false, 'User not found', [], null);
            }

            // Decrypt and check recovery codes
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            // Check if the provided code is valid
            if (!in_array($request->code, $codes)) {
                return apiResponse(false, 'Invalid recovery code', [], null);
            }

            // Replace the used recovery code with a new one
            $codes = array_map(function ($code) use ($request) {
                return $code === $request->code ? Str::random(10) . '-' . Str::random(10) : $code;
            }, $codes);

            // Update recovery codes in the database
            $user->two_factor_recovery_codes = encrypt(json_encode($codes));
            $user->save();

            // Generate a login token
            $token = $user->createToken($user->username);
            $expiresAt = Carbon::now()->addDays(7);

            $latestToken = $user->tokens()->latest()->first();
            if ($latestToken) {
                $latestToken->update(['expires_at' => $expiresAt]);
            }

            // ✅ Store Session Manually
            DB::table('sessions')->insert([
                'id' => session()->getId(), // Generate a session ID
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'payload' => base64_encode(json_encode([
                    'token' => $token,
                ])), // Store the token info securely
                'last_activity' => Carbon::now()->timestamp
            ]);

            // Clear the `_2fa_session` cookie after successful login
            return apiResponse(true, 'Login successful', [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                ],
                'token' => [
                    'accessToken' => $latestToken,
                    'plainTextToken' => $token->plainTextToken,
                ]
            ], null, 200)->withCookie(cookie()->forget('_2fa_session'));

        } catch (\Exception $e) {
            // Handle any unexpected errors
            return apiResponse(false, 'An error occurred while bypassing 2FA with the recovery code',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId User disable 2FA
     */
    public function disable2FA(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null);
            }

            // Check if 2FA is enabled
            if (!$user->two_factor_secret) {
                return apiResponse(false, '2FA is not enabled', [], null);
            }

            // Disable 2FA by setting related fields to null
            $user->two_factor_secret = null;
            $user->two_factor_recovery_codes = null;
            $user->two_factor_confirmed_at = null;
            $user->save();

            return apiResponse(true, '2FA has been disabled successfully', [], null);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return apiResponse(false, 'An error occurred while disabling 2FA',
                $e->getMessage(), 'error');
        }
    }

}
