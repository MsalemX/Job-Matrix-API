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
        Schema::table('reports', function (Blueprint $table) {
            // Drop old constraints and columns
            $table->dropForeign(['user_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['user_id', 'project_id']);

            // Add new columns
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable'); // reportable_id and reportable_type
            $table->enum('status', ['pending', 'dismissed', 'resolved'])->default('pending');
            $table->text('admin_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['reporter_id']);
            $table->dropMorphs('reportable');
            $table->dropColumn(['reporter_id', 'status', 'admin_note']);
            
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        });
    }
};
