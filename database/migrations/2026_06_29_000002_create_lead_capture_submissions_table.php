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
        Schema::create('lead_capture_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_capture_form_id')->constrained('lead_capture_forms')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            // Nullable: the resulting lead (new or matched existing). Set null if lead later deleted.
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');

            // new = created a fresh lead; duplicate = matched an existing lead (preserved, timeline entry added)
            $table->enum('outcome', ['new', 'duplicate']);

            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['lead_capture_form_id', 'submitted_at']);
            $table->index(['created_by', 'outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_capture_submissions');
    }
};
