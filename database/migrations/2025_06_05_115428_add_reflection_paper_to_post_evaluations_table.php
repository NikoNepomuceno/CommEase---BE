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
        Schema::table('post_evaluations', function (Blueprint $table) {
            $table->string('reflection_paper_url')->nullable()->after('recommendation_rating');
            $table->string('reflection_paper_public_id')->nullable()->after('reflection_paper_url');
            $table->string('reflection_paper_filename')->nullable()->after('reflection_paper_public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_evaluations', function (Blueprint $table) {
            $table->dropColumn(['reflection_paper_url', 'reflection_paper_public_id', 'reflection_paper_filename']);
        });
    }
};
