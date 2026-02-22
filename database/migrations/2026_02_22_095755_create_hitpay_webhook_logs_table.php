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
        Schema::create('hitpay_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->index()->nullable();
            $table->string('status')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hitpay_webhook_logs');
    }
};
