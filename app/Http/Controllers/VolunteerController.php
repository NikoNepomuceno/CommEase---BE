<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;


class VolunteerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:volunteer');
    }

    public function registerForEvent(Request $request, Event $event)
    {
        $user = $request->user();

        if (!in_array($event->status, ['pending', 'upcoming'])) {
            return response()->json(['message' => 'Cannot register for this event'], 422);
        }

        if (!in_array($user->program, $event->programs)) {
            return response()->json(['message' => 'This event is not available for your program'], 422);
        }

        if ($event->volunteers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already registered for this event'], 422);
        }

        // Check if event has reached participant limit
        if ($event->is_full) {
            return response()->json([
                'message' => 'This event has reached its participant limit',
                'participant_limit' => $event->participant_limit,
                'current_registrations' => $event->registered_count
            ], 422);
        }

        $event->volunteers()->attach($user->id);

        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'volunteer_registered',
            'message' => "{$user->full_name} has registered for event: {$event->event_title}"
        ]);

        return response()->json([
            'message' => 'Successfully registered for event',
            'user' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'user_email_id' => $user->user_email_id
            ],
            'event' => [
                'id' => $event->id,
                'title' => $event->event_title
            ]
        ]);
    }

    public function unregisterFromEvent(Request $request, Event $event)
    {
        if (!in_array($event->status, ['pending', 'upcoming'])) {
            return response()->json(['message' => 'Cannot unregister from this event'], 422);
        }

        $event->volunteers()->detach($request->user()->id);

        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'volunteer_unregistered',
            'message' => "Volunteer unregistered: {$request->user()->full_name}",
        ]);

        return response()->json(['message' => 'Successfully unregistered from event']);
    }

    public function submitThingsBrought(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'thingsBrought' => ['required', 'array'],
            'thingsBrought.*' => ['required', 'string', 'in:' . implode(',', $event->things_needed)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event->volunteers()->updateExistingPivot($request->user()->id, [
            'things_brought' => $request->thingsBrought
        ]);

        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'things_brought_updated',
            'message' => "Volunteer updated things brought: {$request->user()->full_name}",
        ]);

        return response()->json(['message' => 'Successfully updated things brought']);
    }

    public function submitSuggestion(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'suggestion' => ['required', 'string', 'min:10'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $suggestion = Suggestion::create([
            'event_id' => $event->id,
            'volunteer_id' => $request->user()->id,
            'suggestion' => $request->suggestion,
        ]);

        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'new_suggestion',
            'message' => "New suggestion from: {$request->user()->full_name}",
        ]);

        return response()->json($suggestion, 201);
    }

    public function getEventHistory(Request $request)
    {
        $events = $request->user()
            ->volunteeredEvents()
            ->with(['organizer'])
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->event_title,
                    'date' => $event->date,
                    'status' => $event->status,
                    'organizer' => $event->organizer->full_name,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'things_brought' => $event->pivot->things_brought,
                    'barangay' => $event->barangay ?? 'N/A',
                ];
            });

        return response()->json($events);
    }

    /**
     * Get archived (completed) events for volunteers
     */
    public function getArchivedEvents(Request $request)
    {
        $user = $request->user();

        $query = $user->events()
            ->where('status', 'completed')
            ->with(['organizer']);

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('event_title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Filter by barangay
        if ($request->has('barangay')) {
            $query->where('barangay', $request->barangay);
        }

        $events = $query->orderBy('ended_at', 'desc')
            ->paginate($request->get('per_page', 10));

        // Transform the data to include additional information
        $events->getCollection()->transform(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->event_title,
                'date' => $event->date,
                'status' => $event->status,
                'organizer' => $event->organizer->full_name,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'started_at' => $event->started_at,
                'ended_at' => $event->ended_at,
                'duration' => $event->duration,
                'barangay' => $event->barangay ?? 'N/A',
                'programs' => $event->programs,
                'things_brought' => $event->pivot->things_brought,
                'attendance_status' => $event->pivot->attendance_status,
                'attendance_notes' => $event->pivot->attendance_notes,
                'time_in' => $event->pivot->time_in,
                'time_out' => $event->pivot->time_out,
                'volunteer_duration' => $event->pivot->time_in && $event->pivot->time_out
                    ? (function() use ($event) {
                        $timeIn = $event->pivot->time_in;
                        $timeOut = $event->pivot->time_out;

                        // Ensure we have Carbon instances
                        if (is_string($timeIn)) {
                            $timeIn = \Carbon\Carbon::parse($timeIn);
                        }
                        if (is_string($timeOut)) {
                            $timeOut = \Carbon\Carbon::parse($timeOut);
                        }

                        return $timeIn->diffForHumans($timeOut, true);
                    })()
                    : 'N/A'
            ];
        });

        return response()->json($events);
    }
}
