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
        if (!Schema::hasTable('event_volunteers')) {
            Schema::create('event_volunteers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->json('things_brought')->nullable();
                $table->timestamp('time_in')->nullable();
                $table->timestamp('time_out')->nullable();
                $table->string('attendance_status')->nullable();
                $table->text('attendance_notes')->nullable();
                $table->timestamp('attendance_marked_at')->nullable();
                $table->timestamps();

                // Ensure a volunteer can only register once for an event
                $table->unique(['event_id', 'user_id']);
            });
        } else {
            // Table exists, check and add missing columns
            Schema::table('event_volunteers', function (Blueprint $table) {
                if (!Schema::hasColumn('event_volunteers', 'attendance_status')) {
                    $table->string('attendance_status')->nullable();
                }
                if (!Schema::hasColumn('event_volunteers', 'attendance_notes')) {
                    $table->text('attendance_notes')->nullable();
                }
                if (!Schema::hasColumn('event_volunteers', 'attendance_marked_at')) {
                    $table->timestamp('attendance_marked_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_volunteers');
    }
};
