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
        Schema::create('ai_feedback_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->nullable()->constrained('email_threads')->nullOnDelete();
            $table->foreignId('ai_triage_result_id')->nullable()->constrained('ai_triage_results')->nullOnDelete();
            $table->string('error_type', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'email_thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_feedback_logs');
    }
};
