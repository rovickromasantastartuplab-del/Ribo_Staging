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
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            $table->string('channel'); // fb_lead_ads, messenger, whatsapp, wordpress
            $table->string('external_event_id')->nullable()->index(); // for idempotency
            $table->string('external_actor_key'); // identifying string like phone or psid
            $table->timestamp('received_at');
            $table->string('summary_text');
            $table->text('snippet_text')->nullable();
            $table->boolean('has_media')->default(false);
            $table->string('media_type')->nullable();
            $table->json('payload_min_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
