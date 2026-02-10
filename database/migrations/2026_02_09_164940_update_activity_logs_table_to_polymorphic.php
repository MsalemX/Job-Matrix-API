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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Rename columns to match morph naming convention if possible, 
            // but since we want to be clean, let's just drop and add morphs.
            $table->dropColumn(['target_type', 'target_id']);
            $table->nullableMorphs('loggable');
            $table->json('metadata')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropMorphs('loggable');
            $table->dropColumn('metadata');
            $table->string('target_type')->after('action');
            $table->bigInteger('target_id')->after('target_type');
        });
    }
};
