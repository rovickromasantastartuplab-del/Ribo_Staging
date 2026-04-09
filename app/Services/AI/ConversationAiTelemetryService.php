<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Services\AI\AiUsageCostCalculator;

class ConversationAiTelemetryService
{
    public function recordSuccess(
        int $companyId,
        string $feature,
        ?int $threadId = null,
        ?string $modelVersion = null,
        array $metadata = [],
        int $promptTokens = 0,
        int $completionTokens = 0,
        int $totalTokens = 0
    ): void {
        $cost = app(AiUsageCostCalculator::class)->calculate($modelVersion, $promptTokens, $completionTokens);

        AiUsageLog::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $threadId,
            'feature' => $feature,
            'model_version' => $modelVersion,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $cost,
            'metadata_json' => array_merge($metadata, ['status' => 'success']),
            'requested_at' => now(),
        ]);
    }

    public function recordFailure(
        int $companyId,
        string $feature,
        ?int $threadId = null,
        ?string $modelVersion = null,
        array $metadata = []
    ): void {
        AiUsageLog::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $threadId,
            'feature' => $feature,
            'model_version' => $modelVersion,
            'metadata_json' => array_merge($metadata, ['status' => 'failure']),
            'requested_at' => now(),
        ]);
    }
}
