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
        Schema::create('ai_draft_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->nullable()->constrained('email_threads')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->text('prompt')->nullable();
            $table->string('tone', 50)->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 30)->default('completed');
            $table->string('model_version')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'email_thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_draft_runs');
    }
};
