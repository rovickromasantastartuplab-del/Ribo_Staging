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
        Schema::table('ai_memory_summaries', function (Blueprint $table) {
            $table->foreignId('lead_id')->after('contact_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->index(['created_by', 'lead_id']);
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->foreignId('lead_id')->after('contact_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->index(['created_by', 'lead_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_memory_summaries', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
        });

        Schema::table('ai_tasks', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
        });
    }
};
