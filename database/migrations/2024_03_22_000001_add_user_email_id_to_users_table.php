<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_email_id')->nullable()->after('email');
        });

        // Update existing users with their email IDs
        DB::table('users')->whereNotNull('email')->update([
            'user_email_id' => DB::raw("SUBSTRING_INDEX(email, '@', 1)")
        ]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_email_id');
        });
    }
}; 