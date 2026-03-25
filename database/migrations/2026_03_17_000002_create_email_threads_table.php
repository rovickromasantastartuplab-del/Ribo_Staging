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
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained('gmail_accounts')->onDelete('cascade');
            $table->string('gmail_thread_id')->index(); // Google's thread ID
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();         // Preview text
            $table->json('participants')->nullable();    // Array of email addresses
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_read')->default(true);
            $table->json('labels')->nullable();          // Gmail label IDs
            $table->unsignedBigInteger('created_by');    // Company owner for multi-tenancy
            $table->timestamps();

            // A thread ID is unique per Gmail account
            $table->unique(['gmail_account_id', 'gmail_thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_threads');
    }
};
