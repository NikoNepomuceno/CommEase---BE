<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_title',
        'barangay',
        'organizer_id',
        'programs',
        'date',
        'start_time',
        'end_time',
        'objective',
        'description',
        'things_needed',
        'participant_limit',
        'target_participants',
        'status',
        'started_at',
        'ended_at'
    ];

    protected $casts = [
        'programs' => 'array',
        'things_needed' => 'array',
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the volunteers participating in this event.
     */
    public function volunteers()
    {
        return $this->belongsToMany(User::class, 'event_volunteers', 'event_id', 'user_id')
            ->withPivot(['things_brought', 'time_in', 'time_out', 'attendance_status', 'attendance_notes', 'attendance_marked_at'])
            ->withTimestamps()
            ->withCasts([
                'things_brought' => 'array',
                'time_in' => 'datetime',
                'time_out' => 'datetime',
                'attendance_marked_at' => 'datetime'
            ]);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class);
    }

    public function postEvaluations(): HasMany
    {
        return $this->hasMany(PostEvaluation::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Accessors
    public function getDurationAttribute(): string
    {
        if (!$this->started_at || !$this->ended_at) {
            return 'N/A';
        }
        return $this->started_at->diffForHumans($this->ended_at, true);
    }

    public function getRegisteredCountAttribute(): int
    {
        return $this->volunteers()->count();
    }

    public function getAttendedCountAttribute(): int
    {
        return $this->volunteers()
            ->wherePivot('attendance_status', 'present')
            ->count();
    }

    public function getAverageVolunteerTimeAttribute(): string
    {
        $volunteers = $this->volunteers()
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->get();

        if ($volunteers->isEmpty()) {
            return 'N/A';
        }

        $totalMinutes = $volunteers->sum(function ($volunteer) {
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

        $averageMinutes = $totalMinutes / $volunteers->count();
        return round($averageMinutes) . ' minutes';
    }

    // Participant management methods
    public function getAvailableSlotsAttribute(): int
    {
        return $this->participant_limit - $this->registered_count;
    }

    public function getIsFullAttribute(): bool
    {
        return $this->registered_count >= $this->participant_limit;
    }

    public function getTargetReachedAttribute(): bool
    {
        return $this->registered_count >= $this->target_participants;
    }

    public function getParticipantProgressAttribute(): array
    {
        return [
            'registered' => $this->registered_count,
            'target' => $this->target_participants,
            'limit' => $this->participant_limit,
            'available_slots' => $this->available_slots,
            'target_reached' => $this->target_reached,
            'is_full' => $this->is_full,
            'progress_percentage' => $this->participant_limit > 0
                ? round(($this->registered_count / $this->participant_limit) * 100, 2)
                : 0,
            'target_percentage' => $this->target_participants > 0
                ? round(($this->registered_count / $this->target_participants) * 100, 2)
                : 0
        ];
    }

    // Accessors for time formatting
    public function getStartTimeFormattedAttribute(): string
    {
        return $this->start_time ? Carbon::parse($this->start_time)->format('H:i:s') : '';
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return $this->end_time ? Carbon::parse($this->end_time)->format('H:i:s') : '';
    }

    public function getStartedAtFormattedAttribute(): string
    {
        return $this->started_at ? Carbon::parse($this->started_at)->format('H:i:s') : '';
    }

    public function getEndedAtFormattedAttribute(): string
    {
        return $this->ended_at ? Carbon::parse($this->ended_at)->format('H:i:s') : '';
    }
}
