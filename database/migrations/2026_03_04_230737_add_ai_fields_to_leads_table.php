<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('notes');
            $table->foreignId('ai_suggested_stage_id')->nullable()->constrained('opportunity_stages')->nullOnDelete()->after('last_activity_at');
            $table->integer('ai_confidence_score')->nullable()->after('ai_suggested_stage_id');
            $table->timestamp('ai_last_classified_at')->nullable()->after('ai_confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['ai_suggested_stage_id']);
            $table->dropColumn([
                'last_activity_at',
                'ai_suggested_stage_id',
                'ai_confidence_score',
                'ai_last_classified_at'
            ]);
        });
    }
};
