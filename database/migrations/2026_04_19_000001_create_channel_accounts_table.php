<?php
/**
 * Migration created at 2026-04-19 12:00:00 as part of Omnichannel Inbox refactoring.
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
        Schema::create('channel_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['gmail', 'smtp_imap'])->default('gmail');
            $table->string('email_address');
            
            /**
             * Encrypted JSON configuration. 
             * For Gmail: stores access_token, refresh_token, token_expires_at, google_id, last_history_id.
             * For SMTP/IMAP: stores host, port, encryption, username, password.
             */
            $table->longText('configuration')->nullable(); 
            
            $table->enum('sync_status', ['idle', 'syncing', 'error'])->default('idle');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_accounts');
    }
};
