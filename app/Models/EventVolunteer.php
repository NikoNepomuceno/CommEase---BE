<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVolunteer extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'things_brought',
        'time_in',
        'time_out'
    ];

    protected $casts = [
        'things_brought' => 'array',
        'time_in' => 'datetime',
        'time_out' => 'datetime'
    ];

    /**
     * Get the event that the volunteer is participating in.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user (volunteer) participating in the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 