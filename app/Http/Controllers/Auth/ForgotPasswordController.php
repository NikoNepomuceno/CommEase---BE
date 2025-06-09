<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'ends_with:@gordoncollege.edu.ph', 'exists:users'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = now()->addMinutes(10);

        $user->update([
            'otp' => Hash::make($otp),
            'otp_expires_at' => $otpExpiresAt,
        ]);

        // Send OTP email
        Mail::send('emails.reset-password', ['otp' => $otp], function ($message) use ($user) {
            $message->to($user->email)
                   ->subject('Reset Your Password');
        });

        return response()->json([
            'message' => 'OTP sent successfully',
            'email' => $user->email
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->otp, $user->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        if ($user->otp_expires_at < now()) {
            return response()->json(['message' => 'OTP has expired'], 422);
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'OTP verified successfully',
            'email' => $user->email
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'confirmPassword' => ['required', 'same:password'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->otp !== null) {
            return response()->json(['message' => 'Please verify your OTP first'], 422);
        }

        // Check if the new password is the same as the current password
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'New password cannot be the same as your current password'
            ], 422);
        }

        // Check if the new password is the same as the previous password
        if ($user->previous_password && Hash::check($request->password, $user->previous_password)) {
            return response()->json([
                'message' => 'New password cannot be the same as your previous password'
            ], 422);
        }

        // Store the current password as previous password before updating
        $user->update([
            'previous_password' => $user->password,
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Password reset successfully']);
    }
}
