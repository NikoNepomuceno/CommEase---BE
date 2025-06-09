<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                'regex:/^[0-9]+@gordoncollege\.edu\.ph$/' // Ensures email is in format: numbers@gordoncollege.edu.ph
            ],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:organizer,volunteer',
            'program' => 'required_if:role,volunteer|in:BSIT,BSCS,BSEMC',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Extract user_email_id from email (everything before @)
        $user_email_id = explode('@', $request->email)[0];

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'user_email_id' => $user_email_id, // Automatically set from email
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'program' => $request->program,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'user_email_id' => $user->user_email_id,
            'role' => $user->role,
            'program' => $user->program,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
                'regex:/^[0-9]+@gordoncollege\.edu\.ph$/'
            ],
            'program' => 'sometimes|required_if:role,volunteer|in:BSIT,BSCS,BSEMC',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user fields
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
            // Update user_email_id if email changes
            $user->user_email_id = explode('@', $request->email)[0];
        }
        if ($request->has('program') && $user->role === 'volunteer') {
            $user->program = $request->program;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'user_email_id' => $user->user_email_id,
                'role' => $user->role,
                'program' => $user->program,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    public function getUserQR(Request $request)
    {
        $user = $request->user();
        $qrData = $user->user_email_id; // or $user->email if you prefer
        $qrCode = QrCode::format('png')->size(300)->errorCorrection('H')->generate($qrData);
        $base64QR = base64_encode($qrCode);

        return response()->json([
            'qr_code' => $base64QR,
            'user_email_id' => $user->user_email_id,
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
        ]);
    }
}
