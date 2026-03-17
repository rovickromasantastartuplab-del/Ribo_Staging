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
        Schema::create('gmail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // The company owner
            $table->string('gmail_address');          // Connected email address
            $table->string('google_id');              // Google account ID
            $table->longText('access_token');         // Short-lived OAuth token (encrypted in model)
            $table->longText('refresh_token')->nullable(); // Long-lived refresh token (encrypted in model)
            $table->timestamp('token_expires_at')->nullable();
            $table->text('scopes')->nullable();       // Granted OAuth scopes
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_history_id')->nullable(); // Gmail historyId for incremental sync
            $table->enum('sync_status', ['idle', 'syncing', 'error'])->default('idle');
            $table->text('sync_error')->nullable();
            $table->timestamps();

            // A company can only connect a specific Gmail address once
            $table->unique(['user_id', 'gmail_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_accounts');
    }
};
