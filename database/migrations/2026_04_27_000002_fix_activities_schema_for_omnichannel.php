<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix gmail_account_id constraint in gmail_account_activities table
     * and gmail_message_id in email_messages table.
     * During omnichannel migration, we added channel columns but left
     * legacy gmail columns as NOT NULL, causing inserts to fail.
     */
    public function up(): void
    {
        Schema::table('gmail_account_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('gmail_account_id')->nullable()->change();
        });

        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('gmail_message_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('gmail_message_id')->nullable(false)->change();
        });

        Schema::table('gmail_account_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('gmail_account_id')->nullable(false)->change();
        });
    }
};
