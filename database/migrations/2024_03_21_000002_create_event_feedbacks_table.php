<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('volunteer_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating');
            $table->text('feedback');
            $table->text('improvements')->nullable();
            $table->boolean('would_volunteer_again');
            $table->timestamps();

            $table->unique(['event_id', 'volunteer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_feedbacks');
    }
};
