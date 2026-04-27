<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix two schema issues introduced during omnichannel migration:
     *
     * 1. email_threads.gmail_thread_id — was NOT NULL, but ChannelAccount-based
     *    syncs never provide this column, causing every INSERT to fail.
     *    Fix: make it nullable.
     *
     * 2. channel_accounts.sync_status — ENUM only had ('idle','syncing','error')
     *    but SyncChannelAccountJob writes 'active' on success, causing truncation.
     *    Fix: add 'active' to the ENUM.
     */
    public function up(): void
    {
        // 1. Make gmail_thread_id nullable so omnichannel rows can insert without it.
        Schema::table('email_threads', function (Blueprint $table) {
            $table->string('gmail_thread_id')->nullable()->change();
        });

        // 2. Add 'active' to the sync_status ENUM.
        // Blueprint::enum()->change() doesn't reliably modify ENUMs cross-driver,
        // so we use a raw ALTER TABLE which is safe and precise.
        DB::statement("ALTER TABLE channel_accounts MODIFY COLUMN sync_status ENUM('idle','syncing','active','error') NOT NULL DEFAULT 'idle'");
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // Revert sync_status ENUM (rows with 'active' will become '' — acceptable for rollback)
        DB::statement("ALTER TABLE channel_accounts MODIFY COLUMN sync_status ENUM('idle','syncing','error') NOT NULL DEFAULT 'idle'");

        // Revert gmail_thread_id to NOT NULL (only safe if all rows have a value)
        Schema::table('email_threads', function (Blueprint $table) {
            $table->string('gmail_thread_id')->nullable(false)->change();
        });
    }
};
