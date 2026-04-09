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
        Schema::table('ai_triage_results', function (Blueprint $table) {
            $table->string('thread_state', 30)->nullable()->after('priority');
            $table->string('relationship_health', 30)->nullable()->after('thread_state');
            $table->string('actionability', 30)->nullable()->after('relationship_health');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_triage_results', function (Blueprint $table) {
            $table->dropColumn(['thread_state', 'relationship_health', 'actionability']);
        });
    }
};
