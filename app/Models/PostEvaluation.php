<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'volunteer_id',
        'quality_rating',
        'responsiveness_rating',
        'effectiveness_rating',
        'organization_rating',
        'recommendation_rating',
        'reflection_text',
        'reflection_paper_url',
        'reflection_paper_public_id',
        'reflection_paper_filename'
    ];

    protected $casts = [
        'quality_rating' => 'integer',
        'responsiveness_rating' => 'integer',
        'effectiveness_rating' => 'integer',
        'organization_rating' => 'integer',
        'recommendation_rating' => 'integer'
    ];

    /**
     * Get the event that this evaluation belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the volunteer who submitted this evaluation.
     */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    /**
     * Get the average rating across all questions.
     */
    public function getAverageRatingAttribute(): float
    {
        return round(($this->quality_rating + $this->responsiveness_rating +
                     $this->effectiveness_rating + $this->organization_rating +
                     $this->recommendation_rating) / 5, 2);
    }

    /**
     * Get the evaluation questions as an array.
     */
    public static function getQuestions(): array
    {
        return [
            'quality_rating' => 'How would you rate the overall quality of the community service provided?',
            'responsiveness_rating' => 'How satisfied are you with the responsiveness and helpfulness of the service providers?',
            'effectiveness_rating' => 'How effective was the community service in addressing the needs of the community?',
            'organization_rating' => 'How would you rate the organization and coordination of the community service activities?',
            'recommendation_rating' => 'How likely are you to recommend this community service to others?'
        ];
    }
}
