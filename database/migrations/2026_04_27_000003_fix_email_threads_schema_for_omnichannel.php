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
        Schema::table('email_threads', function (Blueprint $table) {
            $table->unsignedBigInteger('gmail_account_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->unsignedBigInteger('gmail_account_id')->nullable(false)->change();
        });
    }
};
