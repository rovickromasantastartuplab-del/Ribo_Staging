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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // The company owner
            $table->string('provider'); // 'facebook', 'whatsapp', etc.
            $table->string('provider_id'); // e.g. Facebook Page ID
            $table->string('provider_name')->nullable(); // e.g. "Ribo Software"
            $table->longText('access_token');
            $table->longText('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // A company can only connect a specific page once
            $table->unique(['user_id', 'provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
