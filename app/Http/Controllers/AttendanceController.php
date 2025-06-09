<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function scanQR(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'user_email_id' => 'required|string',
            'scan_type' => 'required|in:time_in,time_out'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find user by user_email_id
        $user = User::where('user_email_id', $request->user_email_id)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if user is registered for the event
        if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User is not registered for this event'], 403);
        }

        // Check if event is ongoing
        if ($event->status !== 'ongoing') {
            return response()->json([
                'message' => 'Cannot record attendance. Event is not ongoing.',
                'event_status' => $event->status
            ], 422);
        }

        // Get the volunteer's record for this event
        $volunteerRecord = $event->volunteers()->where('user_id', $user->id)->first();

        // Handle time in
        if ($request->scan_type === 'time_in') {
            if ($volunteerRecord->pivot->time_in) {
                return response()->json(['message' => 'Time in already recorded for this event'], 422);
            }

            // Record time in
            $event->volunteers()->updateExistingPivot($user->id, [
                'time_in' => now()
            ]);

            // Notify organizer
            Notification::create([
                'user_id' => $event->organizer_id,
                'event_id' => $event->id,
                'type' => 'volunteer_time_in',
                'message' => "{$user->full_name} has checked in for event: {$event->event_title}"
            ]);

            return response()->json([
                'message' => 'Time in recorded successfully',
                'status' => 'time_in',
                'time' => now(),
                'event_status' => $event->status
            ]);
        }
        // Handle time out
        else if ($request->scan_type === 'time_out') {
            if (!$volunteerRecord->pivot->time_in) {
                return response()->json(['message' => 'Cannot record time out without time in'], 422);
            }
            if ($volunteerRecord->pivot->time_out) {
                return response()->json(['message' => 'Time out already recorded for this event'], 422);
            }

            // Record time out
            $event->volunteers()->updateExistingPivot($user->id, [
                'time_out' => now()
            ]);

            // Notify organizer
            Notification::create([
                'user_id' => $event->organizer_id,
                'event_id' => $event->id,
                'type' => 'volunteer_time_out',
                'message' => "{$user->full_name} has checked out from event: {$event->event_title}"
            ]);

            return response()->json([
                'message' => 'Time out recorded successfully',
                'status' => 'time_out',
                'time' => now(),
                'event_status' => $event->status
            ]);
        }
    }

    public function getAttendanceStatus(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'user_email_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('user_email_id', $request->user_email_id)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $volunteerRecord = $event->volunteers()
            ->where('user_id', $user->id)
            ->first();

        if (!$volunteerRecord) {
            return response()->json(['message' => 'User is not registered for this event'], 403);
        }

        return response()->json([
            'time_in' => $volunteerRecord->pivot->time_in,
            'time_out' => $volunteerRecord->pivot->time_out,
            'status' => $volunteerRecord->pivot->time_out ? 'completed' : 
                       ($volunteerRecord->pivot->time_in ? 'checked_in' : 'not_checked_in'),
            'event_status' => $event->status
        ]);
    }
} 