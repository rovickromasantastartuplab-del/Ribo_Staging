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
        Schema::create('thread_follow_up_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_thread_id')->constrained('email_threads')->onDelete('cascade');
            $table->tinyInteger('stage_number');
            $table->enum('trigger_type', ['no_reply', 'no_open', 'no_click', 'drip']);
            $table->tinyInteger('delay_days');
            $table->string('subject');
            $table->longText('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_follow_up_stages');
    }
};
