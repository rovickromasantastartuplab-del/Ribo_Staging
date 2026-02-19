<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->longText('config')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_agentables')) {
            Schema::create('ai_agentables', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('ai_agent_id')->nullable()->index();
                $table->integer('ai_agentable_id')->index();
                $table->string('ai_agentable_type', 50)->index();
                $table->timestamps();
                $table->unique(
                    ['ai_agent_id', 'ai_agentable_id', 'ai_agentable_type'],
                    'ai_agentables_unique',
                );
            });
        }

        if (
            Schema::hasIndex(
                'ai_agent_websites',
                'ai_agent_websites_url_hash_unique',
            )
        ) {
            Schema::table('ai_agent_websites', function (Blueprint $table) {
                $table->dropUnique('ai_agent_websites_url_hash_unique');
                $table->index('url_hash');
            });
        }

        Schema::table('ai_agent_webpages', function (Blueprint $table) {
            if (
                Schema::hasIndex(
                    'ai_agent_webpages',
                    'ai_agent_webpages_url_hash_unique',
                )
            ) {
                $table->dropUnique('ai_agent_webpages_url_hash_unique');
            }

            if (
                !Schema::hasIndex(
                    'ai_agent_webpages',
                    'ai_agent_webpages_url_hash_index',
                )
            ) {
                $table->index('url_hash');
            }

            if (
                Schema::hasIndex(
                    'ai_agent_webpages',
                    'ai_agent_webpages_content_hash_unique',
                )
            ) {
                $table->dropUnique('ai_agent_webpages_content_hash_unique');
            }

            if (
                !Schema::hasIndex(
                    'ai_agent_webpages',
                    'ai_agent_webpages_content_hash_index',
                )
            ) {
                $table->index('content_hash');
            }
        });
    }
};
