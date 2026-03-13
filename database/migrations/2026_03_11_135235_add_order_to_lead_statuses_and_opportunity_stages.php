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
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->integer('order')->nullable()->default(0)->after('status');
        });

        Schema::table('opportunity_stages', function (Blueprint $table) {
            $table->integer('order')->nullable()->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunity_stages', function (Blueprint $table) {
            $table->dropColumn('order');
        });

        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
