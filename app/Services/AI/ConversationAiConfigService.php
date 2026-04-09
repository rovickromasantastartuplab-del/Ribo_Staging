<?php

namespace App\Services\AI;

use App\Models\User;

class ConversationAiConfigService
{
    public function resolve(): array
    {
        $superadmin = User::query()->where('type', 'superadmin')->first();
        $superadminId = $superadmin?->id;

        return [
            'enabled' => getSetting('ai_conversation_enabled', '0', $superadminId) === '1',
            'api_key' => trim((string) getSetting('ai_conversation_api_key', '', $superadminId)),
            'model' => (string) getSetting('ai_conversation_model', 'gpt-5.4-mini', $superadminId),
            'timeout_seconds' => (int) getSetting('ai_conversation_timeout_seconds', 30, $superadminId),
        ];
    }
}
