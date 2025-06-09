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
        Schema::table('events', function (Blueprint $table) {
            $table->integer('participant_limit')->after('things_needed')->comment('Maximum number of volunteers that can register');
            $table->integer('target_participants')->after('participant_limit')->comment('Target/ideal number of volunteers for the event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['participant_limit', 'target_participants']);
        });
    }
};
