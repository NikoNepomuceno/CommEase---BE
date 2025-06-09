<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'volunteer_id',
        'rating',
        'feedback',
        'improvements',
        'would_volunteer_again'
    ];

    protected $casts = [
        'rating' => 'integer',
        'would_volunteer_again' => 'boolean'
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }
}
