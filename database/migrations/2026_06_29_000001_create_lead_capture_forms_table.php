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
        Schema::create('lead_capture_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['customer_facing', 'staff_assisted'])->default('customer_facing');

            // Defaults applied to leads created from this form
            $table->foreignId('lead_source_id')->nullable()->constrained('lead_sources')->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->foreignId('default_assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('default_lead_status_id')->nullable()->constrained('lead_statuses')->onDelete('set null');

            // Which lead fields are shown / required. Array of { field, visible, required }.
            $table->json('fields_config')->nullable();

            // Standard branding (all plans)
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->string('submit_button_text')->nullable();
            $table->text('thank_you_message')->nullable();

            // White-label branding (gated behind plan enable_branding). Colors, typography, favicon, seo meta.
            $table->json('theme')->nullable();

            // Optional auto-reply to the customer
            $table->boolean('auto_reply_enabled')->default(false);
            $table->string('auto_reply_subject')->nullable();
            $table->text('auto_reply_body')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_capture_forms');
    }
};
