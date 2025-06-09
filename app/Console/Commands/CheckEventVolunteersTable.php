<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckEventVolunteersTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:event-volunteers-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the event_volunteers table structure and migration status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking event_volunteers table...');

        // Check if table exists
        if (!Schema::hasTable('event_volunteers')) {
            $this->error('❌ event_volunteers table does not exist');
            return 1;
        }

        $this->info('✅ event_volunteers table exists');

        // Check columns
        $expectedColumns = [
            'id',
            'event_id',
            'user_id',
            'things_brought',
            'time_in',
            'time_out',
            'attendance_status',
            'attendance_notes',
            'attendance_marked_at',
            'created_at',
            'updated_at'
        ];

        $this->info('Checking columns...');
        $missingColumns = [];

        foreach ($expectedColumns as $column) {
            if (Schema::hasColumn('event_volunteers', $column)) {
                $this->info("✅ Column '{$column}' exists");
            } else {
                $this->error("❌ Column '{$column}' is missing");
                $missingColumns[] = $column;
            }
        }

        // Check migration status
        $this->info('Checking migration status...');
        $migrationExists = DB::table('migrations')
            ->where('migration', '2024_03_21_000004_create_event_volunteers_table')
            ->exists();

        if ($migrationExists) {
            $this->info('✅ Migration record exists in migrations table');
        } else {
            $this->error('❌ Migration record missing from migrations table');
            $this->info('💡 Run: php artisan migrate:mark-as-run 2024_03_21_000004_create_event_volunteers_table');
        }

        // Summary
        if (empty($missingColumns) && $migrationExists) {
            $this->info('🎉 Everything looks good!');
            return 0;
        } else {
            $this->error('⚠️  Issues found. Please fix them before proceeding.');
            return 1;
        }
    }
}
