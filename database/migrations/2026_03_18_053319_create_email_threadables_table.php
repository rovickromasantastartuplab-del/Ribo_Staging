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
        Schema::create('email_threadables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->morphs('email_threadable');
            $table->string('matched_via')->default('auto');
            $table->timestamps();

            $table->unique(
                ['email_thread_id', 'email_threadable_type', 'email_threadable_id'],
                'threadable_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_threadables');
    }
};
