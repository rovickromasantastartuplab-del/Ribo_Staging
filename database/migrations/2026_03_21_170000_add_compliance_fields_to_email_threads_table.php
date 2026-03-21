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
            $table->string('status')->nullable();
            $table->string('priority')->nullable();
            $table->dateTime('follow_up_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropColumn(['status', 'priority', 'follow_up_at']);
        });
    }
};
