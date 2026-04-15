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
        Schema::create('email_open_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_message_id')->index();
            $table->string('recipient_email');
            $table->string('ip_address')->nullable();
            $table->timestamp('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_open_logs');
    }
};
