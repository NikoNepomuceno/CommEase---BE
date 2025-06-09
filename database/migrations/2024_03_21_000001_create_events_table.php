<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_title');
            $table->string('barangay');
            $table->foreignId('organizer_id')->constrained('users');
            $table->json('programs');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('objective');
            $table->text('description');
            $table->json('things_needed');
            $table->enum('status', ['pending', 'upcoming', 'ongoing', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};
