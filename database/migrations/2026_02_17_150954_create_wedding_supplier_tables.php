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
        Schema::create('wedding_supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('wedding_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('wedding_supplier_categories')
                ->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('telephone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('available_contact_time')->nullable();
            $table->timestamps();
        });

        Schema::create('wedding_supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                ->constrained('wedding_suppliers')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('company_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->comment('References users table where type=company')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('feature_key')->index(); // e.g., 'wedding_suppliers_module'
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'feature_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_feature_flags');
        Schema::dropIfExists('wedding_supplier_contacts');
        Schema::dropIfExists('wedding_suppliers');
        Schema::dropIfExists('wedding_supplier_categories');
    }
};
