<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QRController extends Controller
{
    public function generateQR(Request $request, $eventId)
    {
        try {
            $request->validate([
                'user_email_id' => 'required|string'
            ]);

            $event = Event::findOrFail($eventId);
            $user = User::where('user_email_id', $request->user_email_id)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Check if user is registered for the event
            if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'User is not registered for this event'], 403);
            }

            // Check if event is active
            if ($event->status !== 'active') {
                return response()->json(['message' => 'Event is not active'], 422);
            }

            return response()->json([
                'user' => [
                    'user_email_id' => $user->user_email_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR Generation Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to generate QR code'], 500);
        }
    }

    public function getQRStatus(Request $request, $eventId)
    {
        try {
            $request->validate([
                'user_email_id' => 'required|string'
            ]);

            $event = Event::findOrFail($eventId);
            $user = User::where('user_email_id', $request->user_email_id)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Check if user is registered for the event
            if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'User is not registered for this event'], 403);
            }

            // Get attendance status
            $attendance = $event->attendance()
                ->where('user_id', $user->id)
                ->first();

            return response()->json([
                'status' => $attendance ? 'attended' : 'not_attended',
                'timestamp' => $attendance ? $attendance->created_at : null
            ]);

        } catch (\Exception $e) {
            Log::error('QR Status Check Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to check QR status'], 500);
        }
    }
} 