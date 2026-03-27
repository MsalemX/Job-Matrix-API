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
        DB::table('tasks')
            ->where('status', 'overdue')
            ->update([
                'status' => 'in_progress',
                'updated_at' => now(),
            ]);

        DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending', 'in_progress', 'completed', 'overdue') NOT NULL DEFAULT 'pending'");
    }
};
