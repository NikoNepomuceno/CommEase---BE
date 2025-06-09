<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('volunteer_id')->constrained('users')->onDelete('cascade');

            // The 5 evaluation questions (1-5 star ratings)
            $table->tinyInteger('quality_rating')->comment('How would you rate the overall quality of the community service provided?');
            $table->tinyInteger('responsiveness_rating')->comment('How satisfied are you with the responsiveness and helpfulness of the service providers?');
            $table->tinyInteger('effectiveness_rating')->comment('How effective was the community service in addressing the needs of the community?');
            $table->tinyInteger('organization_rating')->comment('How would you rate the organization and coordination of the community service activities?');
            $table->tinyInteger('recommendation_rating')->comment('How likely are you to recommend this community service to others?');

            $table->timestamps();

            // Ensure one evaluation per volunteer per event
            $table->unique(['event_id', 'volunteer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_evaluations');
    }
};
