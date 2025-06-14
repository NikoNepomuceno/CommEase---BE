<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        Log::info('Login attempt:', ['email' => $request->email]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            Log::error('Login validation failed:', ['errors' => $validator->errors()->toArray()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if input is a student number
        if (preg_match('/^\d{9}$/', $request->email)) {
            $request->email = $request->email . '@gordoncollege.edu.ph';
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            Log::error('Invalid login credentials:', ['email' => $request->email]);
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->email_verified_at) {
            Log::error('Unverified email attempt:', ['email' => $request->email]);
            return response()->json([
                'message' => 'Please verify your email first'
            ], 403);
        }

        // Create token for the user
        $token = $user->createToken('auth-token')->plainTextToken;

        Log::info('Login successful:', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token_created' => true
        ]);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function logout(Request $request)
    {
        Log::info('Logout attempt:', ['user_id' => $request->user()?->id]);

        // Revoke the current token
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        Log::info('Logout successful');
        return response()->json(['message' => 'Logged out successfully']);
    }
}
