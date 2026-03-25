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
        Schema::create('email_thread_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_thread_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Indexing for performance as per "Pros: Very fast querying"
            $table->index('email_thread_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_thread_assignments');
    }
};
