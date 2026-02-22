<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_currency_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->decimal('monthly_price', 15, 2)->default(0);
            $table->decimal('yearly_price', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_currency_prices');
    }
};
