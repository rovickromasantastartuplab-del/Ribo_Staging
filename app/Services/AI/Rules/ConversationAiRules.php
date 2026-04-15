<?php

namespace App\Services\AI\Rules;

use App\Services\AI\ConversationAiConfigService;

class ConversationAiRules
{
    public function __construct(
        private readonly ConversationAiConfigService $configService
    ) {
    }

    public function resolveConfig(): array
    {
        return $this->configService->resolve();
    }

    public function isAvailable(?array $config = null): bool
    {
        $resolved = $config ?? $this->resolveConfig();

        return (bool) ($resolved['enabled'] ?? false)
            && !empty($resolved['api_key']);
    }
}
