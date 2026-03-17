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
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_thread_id')->constrained('email_threads')->onDelete('cascade');
            $table->string('gmail_message_id')->index(); // Google's message ID
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();    // Snippet / first ~200 chars
            $table->longText('body_html')->nullable();   // Full HTML body for display
            $table->timestamp('sent_at');
            $table->json('gmail_labels')->nullable();    // Gmail label IDs on this message
            $table->unsignedBigInteger('created_by');    // Company owner for multi-tenancy
            $table->timestamps();

            // A message ID is unique per thread
            $table->unique(['email_thread_id', 'gmail_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
