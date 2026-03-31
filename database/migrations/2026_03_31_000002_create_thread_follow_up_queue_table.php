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
        Schema::create('thread_follow_up_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_follow_up_stage_id')->constrained('thread_follow_up_stages')->onDelete('cascade');
            $table->string('recipient_email');
            $table->string('gmail_thread_id')->index();
            $table->string('gmail_message_id')->index();
            $table->enum('status', ['pending', 'sent', 'cancelled', 'skipped'])->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_follow_up_queue');
    }
};
