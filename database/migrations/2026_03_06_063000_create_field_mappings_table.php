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
        Schema::create('field_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');                  // company owner
            $table->string('provider', 50);                         // facebook, whatsapp, wordpress
            $table->string('external_field');                       // field name from the external form
            $table->string('crm_field');                            // target CRM field (e.g. name, email, phone, company, notes, value)
            $table->string('default_value')->nullable();            // fallback value when the external field is empty
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'external_field'], 'field_mappings_unique');
            $table->index('user_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_mappings');
    }
};
