<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('event_volunteer', function (Blueprint $table) {
            $table->string('attendance_status')->nullable();
            $table->text('attendance_notes')->nullable();
            $table->timestamp('attendance_marked_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('event_volunteer', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'attendance_notes', 'attendance_marked_at']);
        });
    }
};
