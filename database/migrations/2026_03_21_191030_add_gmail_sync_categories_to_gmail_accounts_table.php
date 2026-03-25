<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gmail_accounts', function (Blueprint $table) {
            $table->json('sync_categories')->nullable()->after('sync_error'); // Stores array of categories
            $table->enum('sync_strategy', ['all', 'categories', 'contacts'])->default('all')->after('sync_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_accounts', function (Blueprint $table) {
            $table->dropColumn(['sync_categories', 'sync_strategy']);
        });
    }
};
