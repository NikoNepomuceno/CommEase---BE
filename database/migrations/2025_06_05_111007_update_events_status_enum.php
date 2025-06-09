<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing 'active' status to 'ongoing' to match the code
        DB::table('events')->where('status', 'active')->update(['status' => 'ongoing']);

        // Modify the enum to include the new status values
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('pending', 'upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'ongoing' status back to 'active'
        DB::table('events')->where('status', 'ongoing')->update(['status' => 'active']);

        // Revert the enum to the original values
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
