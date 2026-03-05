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
        Schema::create('ai_classification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_event_id')->constrained('lead_events')->cascadeOnDelete();
            $table->string('suggested_stage_label');
            $table->foreignId('mapped_stage_id')->nullable()->constrained('opportunity_stages')->nullOnDelete();
            $table->integer('confidence_score');
            $table->json('reasons_json')->nullable();
            $table->string('model_version')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_classification_results');
    }
};
