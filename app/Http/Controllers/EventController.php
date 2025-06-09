<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PostEvaluation;
use App\Models\Notification;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class EventController extends Controller
{
    public function __construct()
    {
        //
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Event::query();

        // Exclude completed events by default (they go to archive)
        // Unless specifically requesting completed events via status filter OR requesting all events
        $requestingAll = $request->has('all') && $request->get('all') === 'true';
        if (!$requestingAll && (!$request->has('status') || $request->status !== 'completed')) {
            $query->where('status', '!=', 'completed');
            \Log::info('Applied completed events filter - excluding completed events');
        } else {
            \Log::info('Skipping completed events filter', [
                'requesting_all' => $requestingAll,
                'has_status' => $request->has('status'),
                'status_value' => $request->get('status')
            ]);
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('event_title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Filter by program
        if ($request->has('program')) {
            $query->whereJsonContains('programs', $request->program);
        }

        // Filter by barangay
        if ($request->has('barangay')) {
            $query->where('barangay', $request->barangay);
        }

        // Check if requesting all events (for calendar display) - already defined above
        \Log::info('EventController::index - Request params:', [
            'has_all' => $request->has('all'),
            'all_value' => $request->get('all'),
            'requesting_all' => $requestingAll,
            'user_role' => $user->role
        ]);

        if (!$requestingAll) {
            // Apply role-based filtering only if not requesting all events
            if ($user->role === 'organizer') {
                $query->where('organizer_id', $user->id);
                \Log::info('Applied organizer filter');
            } else {
                // For volunteers, show all events that match their program
                $query->whereJsonContains('programs', $user->program);
                \Log::info('Applied volunteer program filter', ['program' => $user->program]);
            }
        } else {
            \Log::info('Skipping role-based filtering - showing all events');
        }

        // Debug: Log the SQL query that will be executed
        \Log::info('SQL Query to be executed:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // If requesting all events, don't paginate
        if ($requestingAll) {
            $events = $query->with(['organizer', 'volunteers'])
                ->orderBy('date')
                ->get();
            \Log::info('Returning all events without pagination');
        } else {
            $events = $query->with(['organizer', 'volunteers'])
                ->orderBy('date')
                ->paginate($request->get('per_page', 10));
            \Log::info('Returning paginated events');
        }

        // Log the events found
        \Log::info('Events found:', [
            'requesting_all' => $requestingAll,
            'count' => $events->count(),
            'first_few_events' => $requestingAll
                ? $events->take(3)->map(function($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->event_title,
                        'status' => $event->status,
                        'organizer_id' => $event->organizer_id
                    ];
                })
                : $events->getCollection()->take(3)->map(function($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->event_title,
                        'status' => $event->status,
                        'organizer_id' => $event->organizer_id
                    ];
                })
        ]);

        // Append formatted time attributes and participant progress to each event
        if ($requestingAll) {
            // For non-paginated results (Collection)
            $events->transform(function ($event) {
                $event->append([
                    'start_time_formatted',
                    'end_time_formatted',
                    'started_at_formatted',
                    'ended_at_formatted',
                    'registered_count',
                    'participant_progress',
                    'available_slots',
                    'is_full',
                    'target_reached'
                ]);
                return $event;
            });
        } else {
            // For paginated results (LengthAwarePaginator)
            $events->getCollection()->transform(function ($event) {
                $event->append([
                    'start_time_formatted',
                    'end_time_formatted',
                    'started_at_formatted',
                    'ended_at_formatted',
                    'registered_count',
                    'participant_progress',
                    'available_slots',
                    'is_full',
                    'target_reached'
                ]);
                return $event;
            });
        }

        return response()->json($events);
    }

    public function store(Request $request)
    {
        \Log::info('Create Event Request Data:', [
            'request_data' => $request->all(),
            'user' => $request->user(),
            'headers' => $request->headers->all()
        ]);

        $validator = Validator::make($request->all(), [
            'eventTitle' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string'],
            'programs' => ['required', 'array'],
            'programs.*' => ['required', Rule::in(['BSIT', 'BSCS', 'BSEMC'])],
            'date' => ['required', 'date', 'after:today'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'objective' => ['required', 'string'],
            'description' => ['required', 'string'],
            'thingsNeeded' => ['required', 'array'],
            'thingsNeeded.*' => ['required', 'string'],
            'participantLimit' => ['required', 'integer', 'min:1', 'max:1000'],
            'targetParticipants' => ['required', 'integer', 'min:1', 'lte:participantLimit'],
        ]);

        if ($validator->fails()) {
            \Log::error('Event Creation Validation Failed:', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Format the date and times
            $date = $request->date;
            $startTime = $request->startTime;
            $endTime = $request->endTime;

            $event = Event::create([
                'event_title' => $request->eventTitle,
                'barangay' => $request->barangay,
                'organizer_id' => $request->user()->id,
                'programs' => $request->programs,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'objective' => $request->objective,
                'description' => $request->description,
                'things_needed' => $request->thingsNeeded,
                'participant_limit' => $request->participantLimit,
                'target_participants' => $request->targetParticipants,
            ]);

            \Log::info('Event Created Successfully:', [
                'event_id' => $event->id,
                'event_data' => $event->toArray()
            ]);

            // Notify eligible volunteers
            $eligibleVolunteers = User::whereIn('program', $request->programs)
                ->where('role', 'volunteer')
                ->get();

            foreach ($eligibleVolunteers as $volunteer) {
                Notification::create([
                    'user_id' => $volunteer->id,
                    'event_id' => $event->id,
                    'type' => 'new_event',
                    'message' => "New event available: {$event->event_title}",
                ]);
            }

            return response()->json($event, 201);
        } catch (\Exception $e) {
            \Log::error('Event Creation Failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to create event', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Event $event)
    {
        $event->load(['organizer', 'volunteers']);
        $event->append([
            'start_time_formatted',
            'end_time_formatted',
            'started_at_formatted',
            'ended_at_formatted',
            'registered_count',
            'participant_progress',
            'available_slots',
            'is_full',
            'target_reached'
        ]);
        return response()->json($event);
    }

    public function update(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($event->date->subDay()->isPast()) {
            return response()->json(['message' => 'Cannot update event less than 1 day away'], 422);
        }

        $validator = Validator::make($request->all(), [
            'eventTitle' => ['sometimes', 'required', 'string', 'max:255'],
            'barangay' => ['sometimes', 'required', 'string'],
            'programs' => ['sometimes', 'required', 'array'],
            'programs.*' => ['required', Rule::in(['BSIT', 'BSCS', 'BSEMC'])],
            'date' => ['sometimes', 'required', 'date', 'after:today'],
            'startTime' => ['sometimes', 'required', 'date_format:H:i'],
            'endTime' => ['sometimes', 'required', 'date_format:H:i', 'after:startTime'],
            'objective' => ['sometimes', 'required', 'string'],
            'description' => ['sometimes', 'required', 'string'],
            'thingsNeeded' => ['sometimes', 'required', 'array'],
            'thingsNeeded.*' => ['required', 'string'],
            'participantLimit' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'targetParticipants' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Additional validation for participant limits
        if ($request->has('participantLimit') && $request->has('targetParticipants')) {
            if ($request->targetParticipants > $request->participantLimit) {
                return response()->json([
                    'errors' => ['targetParticipants' => ['Target participants cannot exceed participant limit']]
                ], 422);
            }
        } elseif ($request->has('targetParticipants')) {
            if ($request->targetParticipants > $event->participant_limit) {
                return response()->json([
                    'errors' => ['targetParticipants' => ['Target participants cannot exceed current participant limit']]
                ], 422);
            }
        } elseif ($request->has('participantLimit')) {
            if ($request->participantLimit < $event->target_participants) {
                return response()->json([
                    'errors' => ['participantLimit' => ['Participant limit cannot be less than current target participants']]
                ], 422);
            }
        }

        // Format the date and times
        $date = $request->date ?? $event->date;
        $startTime = $request->startTime ?? $event->start_time;
        $endTime = $request->endTime ?? $event->end_time;

        $event->update([
            'event_title' => $request->eventTitle ?? $event->event_title,
            'barangay' => $request->barangay ?? $event->barangay,
            'programs' => $request->programs ?? $event->programs,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'objective' => $request->objective ?? $event->objective,
            'description' => $request->description ?? $event->description,
            'things_needed' => $request->thingsNeeded ?? $event->things_needed,
            'participant_limit' => $request->participantLimit ?? $event->participant_limit,
            'target_participants' => $request->targetParticipants ?? $event->target_participants,
        ]);

        // Notify registered volunteers
        foreach ($event->volunteers as $volunteer) {
            Notification::create([
                'user_id' => $volunteer->id,
                'event_id' => $event->id,
                'type' => 'event_updated',
                'message' => "Event updated: {$event->event_title}",
            ]);
        }

        return response()->json($event);
    }

    public function destroy(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Notify registered volunteers
        foreach ($event->volunteers as $volunteer) {
            Notification::create([
                'user_id' => $volunteer->id,
                'event_id' => $event->id,
                'type' => 'event_cancelled',
                'message' => "Event cancelled: {$event->event_title}",
            ]);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    public function startEvent(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($event->status, ['pending', 'upcoming'])) {
            return response()->json(['message' => 'Event can only be started when in pending or upcoming status'], 422);
        }

        $event->update([
            'status' => 'ongoing',
            'started_at' => now()
        ]);

        // Notify registered volunteers
        foreach ($event->volunteers as $volunteer) {
            Notification::create([
                'user_id' => $volunteer->id,
                'event_id' => $event->id,
                'type' => 'event_started',
                'message' => "Event started: {$event->event_title}",
            ]);
        }

        return response()->json($event);
    }

    public function endEvent(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($event->status !== 'ongoing') {
            return response()->json(['message' => 'Event can only be ended when in ongoing status'], 422);
        }

        $event->update([
            'status' => 'completed',
            'ended_at' => now()
        ]);

        // Notify registered volunteers
        foreach ($event->volunteers as $volunteer) {
            Notification::create([
                'user_id' => $volunteer->id,
                'event_id' => $event->id,
                'type' => 'event_ended',
                'message' => "Event ended: {$event->event_title}",
            ]);
        }

        return response()->json($event);
    }

    public function markAttendance(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'volunteer_id' => ['required', 'exists:users,id'],
            'status' => ['required', 'in:present,absent'],
            'notes' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event->volunteers()->updateExistingPivot($request->volunteer_id, [
            'attendance_status' => $request->status,
            'attendance_notes' => $request->notes,
            'attendance_marked_at' => now()
        ]);

        Notification::create([
            'user_id' => $request->volunteer_id,
            'event_id' => $event->id,
            'type' => 'attendance_marked',
            'message' => "Your attendance has been marked as {$request->status} for event: {$event->event_title}",
        ]);

        return response()->json(['message' => 'Attendance marked successfully']);
    }

    public function getAttendance(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attendance = $event->volunteers()
            ->select('users.id', 'users.full_name', 'users.email', 'event_volunteers.attendance_status', 'event_volunteers.attendance_notes', 'event_volunteers.attendance_marked_at')
            ->get();

        return response()->json($attendance);
    }

    public function getAnalytics(Event $event)
    {
        // Check if user is the organizer of this event
        if ($event->organizer_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Basic event statistics
        $registeredCount = $event->registered_count;

        // Enhanced attendance analysis based on time tracking
        $allVolunteers = $event->volunteers()->get();

        // Present: Has both time in and time out
        $presentCount = $allVolunteers->filter(function ($volunteer) {
            return $volunteer->pivot->time_in && $volunteer->pivot->time_out;
        })->count();

        // No time out: Has time in but no time out
        $noTimeOutCount = $allVolunteers->filter(function ($volunteer) {
            return $volunteer->pivot->time_in && !$volunteer->pivot->time_out;
        })->count();

        // Absent: No time in (regardless of attendance status)
        $absentCount = $allVolunteers->filter(function ($volunteer) {
            return !$volunteer->pivot->time_in;
        })->count();

        // Attendance rate calculation (present + no time out = attended)
        $attendedCount = $presentCount + $noTimeOutCount;
        $attendanceRate = $registeredCount > 0 ? round(($attendedCount / $registeredCount) * 100, 2) : 0;

        // Things brought analysis
        $rawThingsData = $event->volunteers()
            ->whereNotNull('things_brought')
            ->get()
            ->pluck('pivot.things_brought')
            ->filter();

        // Debug: Log raw data
        \Log::info('Raw things brought data:', $rawThingsData->toArray());

        $thingsBroughtData = $rawThingsData
            ->map(function ($items) {
                // Ensure we have an array and decode if it's JSON
                if (is_string($items)) {
                    $decoded = json_decode($items, true);
                    return is_array($decoded) ? $decoded : [$items];
                }
                return is_array($items) ? $items : [$items];
            })
            ->flatten()
            ->filter(function ($item) {
                // Remove empty strings and null values
                return !empty(trim($item));
            })
            ->map(function ($item) {
                // Clean and normalize item names
                return trim(strtolower($item));
            })
            ->countBy()
            ->map(function ($count, $item) {
                // Capitalize first letter for display
                return ['item' => ucfirst($item), 'count' => $count];
            })
            ->mapWithKeys(function ($data) {
                return [$data['item'] => $data['count']];
            })
            ->toArray();

        // Debug: Log processed data
        \Log::info('Processed things brought data:', $thingsBroughtData);

        // Volunteer time analysis
        $volunteersWithTime = $event->volunteers()
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->get();

        $timeAnalysis = [
            'total_volunteers_with_time' => $volunteersWithTime->count(),
            'average_time_minutes' => 0,
            'total_volunteer_hours' => 0,
            'longest_session_minutes' => 0,
            'shortest_session_minutes' => 0
        ];

        if ($volunteersWithTime->isNotEmpty()) {
            $times = $volunteersWithTime->map(function ($volunteer) {
                $timeIn = $volunteer->pivot->time_in;
                $timeOut = $volunteer->pivot->time_out;

                // Ensure we have Carbon instances
                if (is_string($timeIn)) {
                    $timeIn = \Carbon\Carbon::parse($timeIn);
                }
                if (is_string($timeOut)) {
                    $timeOut = \Carbon\Carbon::parse($timeOut);
                }

                return $timeIn->diffInMinutes($timeOut);
            });

            $timeAnalysis['average_time_minutes'] = round($times->avg());
            $timeAnalysis['total_volunteer_hours'] = round($times->sum() / 60, 2);
            $timeAnalysis['longest_session_minutes'] = $times->max();
            $timeAnalysis['shortest_session_minutes'] = $times->min();
        }

        // Absentees list
        $absentees = $event->volunteers()
            ->wherePivot('attendance_status', '!=', 'present')
            ->orWherePivot('attendance_status', null)
            ->get()
            ->map(function ($volunteer) {
                return [
                    'id' => $volunteer->id,
                    'name' => $volunteer->full_name, // This will use the accessor
                    'email' => $volunteer->email,
                    'program' => $volunteer->program,
                    'attendance_status' => $volunteer->pivot->attendance_status ?? 'not_marked'
                ];
            });

        // Program distribution
        $programDistribution = $event->volunteers()
            ->get()
            ->groupBy('program')
            ->map(function ($volunteers) {
                return $volunteers->count();
            })
            ->toArray();

        // Volunteer time tracking details
        $volunteerTimeDetails = $event->volunteers()
            ->get()
            ->map(function ($volunteer) {
                $timeIn = $volunteer->pivot->time_in;
                $timeOut = $volunteer->pivot->time_out;
                $duration = null;

                if ($timeIn && $timeOut) {
                    // Ensure we have Carbon instances
                    if (is_string($timeIn)) {
                        $timeIn = \Carbon\Carbon::parse($timeIn);
                    }
                    if (is_string($timeOut)) {
                        $timeOut = \Carbon\Carbon::parse($timeOut);
                    }

                    $duration = $timeIn->diffInMinutes($timeOut);
                }

                return [
                    'id' => $volunteer->id,
                    'name' => $volunteer->full_name,
                    'program' => $volunteer->program,
                    'time_in' => $timeIn ? (is_string($timeIn) ? $timeIn : $timeIn->format('H:i:s')) : null,
                    'time_out' => $timeOut ? (is_string($timeOut) ? $timeOut : $timeOut->format('H:i:s')) : null,
                    'duration_minutes' => $duration,
                    'duration_hours' => $duration ? round($duration / 60, 2) : null,
                    'attendance_status' => $volunteer->pivot->attendance_status,
                    'things_brought' => $volunteer->pivot->things_brought
                ];
            })
            ->sortByDesc('duration_minutes');

        // Participant progress
        $participantProgress = $event->participant_progress;

        return response()->json([
            // Basic metrics
            'duration' => $event->duration,
            'registered_count' => $registeredCount,
            'attended_count' => $attendedCount,
            'absent_count' => $absentCount,
            'attendance_rate' => $attendanceRate,

            // Enhanced attendance breakdown
            'present_count' => $presentCount,
            'no_time_out_count' => $noTimeOutCount,
            'absent_count' => $absentCount,

            // Participant progress
            'participant_progress' => $participantProgress,

            // Time analysis
            'time_analysis' => $timeAnalysis,
            'average_volunteer_time' => $event->average_volunteer_time,

            // Things brought
            'things_brought' => $thingsBroughtData,
            'things_brought_total' => array_sum($thingsBroughtData),

            // Program distribution
            'program_distribution' => $programDistribution,

            // Volunteer time tracking
            'volunteer_time_details' => $volunteerTimeDetails->values(),

            // Detailed lists
            'absentees' => $absentees,

            // Event details
            'event_status' => $event->status,
            'started_at' => $event->started_at,
            'ended_at' => $event->ended_at,
            'planned_start' => $event->start_time,
            'planned_end' => $event->end_time,
        ]);
    }

    public function submitFeedback(Request $request, Event $event)
    {
        if (!$event->volunteers()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'You must be a registered volunteer to submit feedback'], 403);
        }

        if ($event->status !== 'completed') {
            return response()->json(['message' => 'Feedback can only be submitted for completed events'], 422);
        }

        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['required', 'string', 'min:10'],
            'improvements' => ['nullable', 'string'],
            'would_volunteer_again' => ['required', 'boolean']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback = $event->feedbacks()->create([
            'volunteer_id' => $request->user()->id,
            'rating' => $request->rating,
            'feedback' => $request->feedback,
            'improvements' => $request->improvements,
            'would_volunteer_again' => $request->would_volunteer_again
        ]);

        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'new_feedback',
            'message' => "New feedback received for event: {$event->event_title}",
        ]);

        return response()->json($feedback, 201);
    }

    public function getFeedback(Request $request, Event $event)
    {
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $feedback = $event->feedbacks()
            ->with('volunteer:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'average_rating' => $feedback->avg('rating'),
            'total_feedback' => $feedback->count(),
            'would_volunteer_again_percentage' => ($feedback->where('would_volunteer_again', true)->count() / $feedback->count()) * 100,
            'rating_distribution' => $feedback->groupBy('rating')
                ->map(fn($group) => $group->count())
                ->toArray()
        ];

        return response()->json([
            'feedback' => $feedback,
            'stats' => $stats
        ]);
    }

    public function register(Request $request, Event $event)
    {
        $user = $request->user();

        // Check if user is a volunteer
        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'Only volunteers can register for events'], 403);
        }

        // Check if event is still open for registration
        if ($event->status !== 'pending') {
            return response()->json(['message' => 'Event is no longer open for registration'], 422);
        }

        // Check if volunteer's program matches event requirements
        if (!in_array($user->program, $event->programs)) {
            return response()->json(['message' => 'Your program is not eligible for this event'], 403);
        }

        // Check if already registered
        if ($event->volunteers()->where('volunteer_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already registered for this event'], 422);
        }

        // Register volunteer
        $event->volunteers()->attach($user->id);

        // Notify organizer
        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'volunteer_registered',
            'message' => "New volunteer registered: {$user->full_name}",
        ]);

        return response()->json(['message' => 'Successfully registered for the event']);
    }

    public function submitThingsBrought(Request $request, Event $event)
    {
        $user = $request->user();

        // Check if user is registered for the event
        if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You must be registered for this event'], 403);
        }

        $validator = Validator::make($request->all(), [
            'things_brought' => ['required', 'array'],
            'things_brought.*' => ['required', 'string', 'max:255']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update things brought
        $event->volunteers()->updateExistingPivot($user->id, [
            'things_brought' => $request->things_brought
        ]);

        // Notify organizer
        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'things_brought_updated',
            'message' => "Volunteer {$user->full_name} updated things they will bring",
        ]);

        return response()->json(['message' => 'Successfully updated things you will bring']);
    }

    public function submitSuggestion(Request $request, Event $event)
    {
        $user = $request->user();

        // Check if user is registered for the event
        if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You must be registered for this event'], 403);
        }

        $validator = Validator::make($request->all(), [
            'suggestion' => ['required', 'string', 'min:10', 'max:1000'],
            'category' => ['required', 'string', 'in:logistics,program,venue,other']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create suggestion
        $suggestion = $event->suggestions()->create([
            'volunteer_id' => $user->id,
            'suggestion' => $request->suggestion
        ]);

        // Notify organizer
        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'new_suggestion',
            'message' => "New suggestion from {$user->full_name}",
        ]);

        return response()->json($suggestion, 201);
    }

    /**
     * Get archived (completed) events for organizers
     */
    public function getArchivedEvents(Request $request)
    {
        \Log::info('=== ARCHIVED EVENTS DEBUG ===');
        \Log::info('Session ID:', [session()->getId()]);
        \Log::info('Auth check:', [\Auth::check()]);
        \Log::info('Request user:', [$request->user()]);

        if ($request->user()) {
            \Log::info('User details:', [
                'id' => $request->user()->id,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'role_type' => gettype($request->user()->role),
                'attributes' => $request->user()->getAttributes()
            ]);
        } else {
            \Log::error('NO USER FOUND IN REQUEST');
        }

        // Simple query - just get all completed events
        $events = Event::where('status', 'completed')
            ->with(['organizer', 'volunteers'])
            ->orderBy('created_at', 'desc')
            ->get();

        \Log::info('Found completed events:', [
            'count' => $events->count(),
        ]);

        return response()->json([
            'message' => 'Success - bypassed role check',
            'user_role' => $request->user() ? $request->user()->role : 'no user',
            'data' => $events
        ]);
    }

    /**
     * Submit post-evaluation for a completed event (volunteers only)
     */
    public function submitPostEvaluation(Request $request, Event $event)
    {
        $user = $request->user();

        // Check if user is a volunteer
        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'Only volunteers can submit post-evaluations'], 403);
        }

        // Check if user participated in the event
        if (!$event->volunteers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You must have participated in this event to submit an evaluation'], 403);
        }

        // Check if event is completed
        if ($event->status !== 'completed') {
            return response()->json(['message' => 'Post-evaluation can only be submitted for completed events'], 422);
        }

        // Check if evaluation already exists
        if ($event->postEvaluations()->where('volunteer_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already submitted an evaluation for this event'], 422);
        }

        $validator = Validator::make($request->all(), [
            'quality_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'responsiveness_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'effectiveness_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'organization_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'recommendation_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'reflection_text' => ['required', 'string', 'min:50'], // Minimum 50 words (roughly 250 characters)
            'reflection_paper' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt'] // Keep for backward compatibility
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle reflection paper upload if provided
        $reflectionData = [];
        if ($request->hasFile('reflection_paper')) {
            $cloudinaryService = app(CloudinaryService::class);
            $file = $request->file('reflection_paper');

            // Validate file
            $validation = $cloudinaryService->validateFile($file);
            if (!$validation['valid']) {
                return response()->json([
                    'message' => 'Reflection paper validation failed',
                    'errors' => $validation['errors']
                ], 422);
            }

            // Upload to Cloudinary
            $uploadResult = $cloudinaryService->uploadReflectionPaper($file, $event->id, $user->id);
            if (!$uploadResult['success']) {
                return response()->json([
                    'message' => 'Failed to upload reflection paper',
                    'error' => $uploadResult['error']
                ], 500);
            }

            $reflectionData = [
                'reflection_paper_url' => $uploadResult['url'],
                'reflection_paper_public_id' => $uploadResult['public_id'],
                'reflection_paper_filename' => $uploadResult['original_filename']
            ];
        }

        $evaluation = $event->postEvaluations()->create(array_merge([
            'volunteer_id' => $user->id,
            'quality_rating' => $request->quality_rating,
            'responsiveness_rating' => $request->responsiveness_rating,
            'effectiveness_rating' => $request->effectiveness_rating,
            'organization_rating' => $request->organization_rating,
            'recommendation_rating' => $request->recommendation_rating,
            'reflection_text' => $request->reflection_text, // Add reflection text
        ], $reflectionData));

        // Notify organizer
        Notification::create([
            'user_id' => $event->organizer_id,
            'event_id' => $event->id,
            'type' => 'new_post_evaluation',
            'message' => "New post-evaluation received from {$user->full_name} for event: {$event->event_title}",
        ]);

        return response()->json([
            'message' => 'Post-evaluation submitted successfully',
            'evaluation' => $evaluation,
            'average_rating' => $evaluation->average_rating
        ], 201);
    }

    /**
     * Get post-evaluations for an event (organizers only)
     */
    public function getPostEvaluations(Request $request, Event $event)
    {
        $user = $request->user();

        // Check if user is the organizer
        if ($user->role !== 'organizer' || $event->organizer_id !== $user->id) {
            return response()->json(['message' => 'Only the event organizer can view post-evaluations'], 403);
        }

        $evaluations = $event->postEvaluations()
            ->with('volunteer:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($evaluations->isEmpty()) {
            return response()->json([
                'evaluations' => [],
                'stats' => null,
                'questions' => PostEvaluation::getQuestions()
            ]);
        }

        // Calculate statistics
        $stats = [
            'total_evaluations' => $evaluations->count(),
            'average_ratings' => [
                'quality' => round($evaluations->avg('quality_rating'), 2),
                'responsiveness' => round($evaluations->avg('responsiveness_rating'), 2),
                'effectiveness' => round($evaluations->avg('effectiveness_rating'), 2),
                'organization' => round($evaluations->avg('organization_rating'), 2),
                'recommendation' => round($evaluations->avg('recommendation_rating'), 2),
                'overall' => round($evaluations->avg(function($eval) {
                    return $eval->average_rating;
                }), 2)
            ],
            'rating_distribution' => [
                'quality' => $evaluations->groupBy('quality_rating')->map(fn($group) => $group->count())->toArray(),
                'responsiveness' => $evaluations->groupBy('responsiveness_rating')->map(fn($group) => $group->count())->toArray(),
                'effectiveness' => $evaluations->groupBy('effectiveness_rating')->map(fn($group) => $group->count())->toArray(),
                'organization' => $evaluations->groupBy('organization_rating')->map(fn($group) => $group->count())->toArray(),
                'recommendation' => $evaluations->groupBy('recommendation_rating')->map(fn($group) => $group->count())->toArray()
            ]
        ];

        return response()->json([
            'evaluations' => $evaluations,
            'stats' => $stats,
            'questions' => PostEvaluation::getQuestions()
        ]);
    }

    public function getSuggestions(Request $request, Event $event)
    {
        // Check if user is the organizer of this event
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $suggestions = $event->suggestions()
            ->with('volunteer:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'suggestions' => $suggestions,
            'total_suggestions' => $suggestions->count()
        ]);
    }

    /**
     * Get evaluation questions (public endpoint)
     */
    public function getEvaluationQuestions()
    {
        return response()->json([
            'questions' => PostEvaluation::getQuestions()
        ]);
    }
}
