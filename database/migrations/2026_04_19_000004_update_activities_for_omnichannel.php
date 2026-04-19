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
        Schema::table('gmail_account_activities', function (Blueprint $table) {
            $table->foreignId('channel_account_id')->nullable()->after('gmail_account_id')->constrained('channel_accounts')->onDelete('cascade');
            $table->index(['channel_account_id', 'activity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_account_activities', function (Blueprint $table) {
            $table->dropForeign(['channel_account_id']);
            $table->dropColumn('channel_account_id');
        });
    }
};
