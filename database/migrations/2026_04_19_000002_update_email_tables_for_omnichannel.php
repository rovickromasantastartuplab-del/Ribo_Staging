<?php
/**
 * Migration created at 2026-04-19 12:05:00 as part of Omnichannel Inbox refactoring.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update email_threads
        Schema::table('email_threads', function (Blueprint $table) {
            $table->foreignId('channel_account_id')->nullable()->after('gmail_account_id')->constrained('channel_accounts')->onDelete('cascade');
            $table->string('channel_type')->nullable()->after('channel_account_id'); // e.g., 'gmail', 'smtp_imap'
            $table->string('external_thread_id')->nullable()->after('gmail_thread_id'); // Provider-agnostic thread ID
            
            // Add index for fast retrieval of omnichannel threads
            $table->index(['channel_account_id', 'external_thread_id']);
        });

        // Update email_messages
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('external_message_id')->nullable()->after('gmail_message_id');
            $table->json('tags')->nullable()->after('gmail_labels'); // Generic version of gmail_labels
            $table->json('metadata')->nullable()->after('tags'); // Future-proofing
            
            $table->index(['email_thread_id', 'external_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropIndex(['channel_account_id', 'external_thread_id']);
            $table->dropForeign(['channel_account_id']);
            $table->dropColumn(['channel_account_id', 'channel_type', 'external_thread_id']);
        });

        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex(['email_thread_id', 'external_message_id']);
            $table->dropColumn(['external_message_id', 'tags', 'metadata']);
        });
    }
};
