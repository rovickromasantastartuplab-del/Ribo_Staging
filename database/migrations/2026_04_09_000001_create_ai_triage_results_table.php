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
        Schema::create('ai_triage_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->string('intent', 50)->nullable();
            $table->unsignedTinyInteger('intent_confidence')->nullable();
            $table->string('priority', 20)->nullable();
            $table->unsignedTinyInteger('success_probability')->nullable();
            $table->string('behavioral_pulse', 30)->nullable();
            $table->text('summary')->nullable();
            $table->json('strategic_action_json')->nullable();
            $table->string('model_version')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['created_by', 'email_thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_triage_results');
    }
};
