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
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('facebook_psid')->nullable()->after('phone');
            $table->string('facebook_page_id')->nullable()->after('facebook_psid');
            $table->string('whatsapp_phone_e164')->nullable()->after('facebook_page_id');
            $table->string('last_inbound_channel')->nullable()->after('whatsapp_phone_e164');
            $table->timestamp('last_inbound_at')->nullable()->after('last_inbound_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_psid',
                'facebook_page_id',
                'whatsapp_phone_e164',
                'last_inbound_channel',
                'last_inbound_at'
            ]);
        });
    }
};
