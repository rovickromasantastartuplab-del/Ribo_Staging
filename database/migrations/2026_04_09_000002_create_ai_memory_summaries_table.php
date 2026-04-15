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
        Schema::create('ai_memory_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->text('relationship_summary')->nullable();
            $table->string('relationship_strength', 30)->nullable();
            $table->json('memory_points_json')->nullable();
            $table->string('model_version')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('summarized_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_memory_summaries');
    }
};
