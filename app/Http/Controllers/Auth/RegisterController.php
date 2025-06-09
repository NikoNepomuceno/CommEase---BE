<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => ['required', 'string', 'regex:/^[a-zA-Z\s]+$/'],
            'middleInitial' => ['nullable', 'string', 'size:1'],
            'lastName' => ['required', 'string', 'regex:/^[a-zA-Z\s]+$/'],
            'program' => ['required', 'string', Rule::in(['BSIT', 'BSCS', 'BSEMC'])],
            'email' => ['required', 'email', 'ends_with:@gordoncollege.edu.ph', 'unique:users'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = now()->addMinutes(10);

        $user = User::create([
            'first_name' => $request->firstName,
            'middle_initial' => $request->middleInitial,
            'last_name' => $request->lastName,
            'program' => $request->program,
            'email' => $request->email,
            'otp' => Hash::make($otp),
            'otp_expires_at' => $otpExpiresAt,
        ]);

        // Send OTP email
        Mail::send('emails.verify-email', ['otp' => $otp], function ($message) use ($user) {
            $message->to($user->email)
                   ->subject('Verify Your Email Address');
        });

        return response()->json([
            'message' => 'Registration successful. Please check your email for OTP verification.',
            'email' => $user->email
        ], 201);
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
            'email_verified_at' => now()
        ]);

        return response()->json([
            'message' => 'OTP verified successfully',
            'email' => $user->email
        ]);
    }

    public function createPassword(Request $request)
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
            return response()->json(['message' => 'Please verify your email first'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Password created successfully']);
    }
}
