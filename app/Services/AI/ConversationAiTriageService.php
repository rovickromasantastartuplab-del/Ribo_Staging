<?php

namespace App\Services\AI;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Skills\TriageSkill;

class ConversationAiTriageService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly TriageSkill $triageSkill
    ) {
    }

    public function show(EmailThread $thread, int $companyId): AiTriageResult
    {
        $existing = AiTriageResult::query()
            ->where('created_by', $companyId)
            ->where('email_thread_id', $thread->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->refresh($thread, $companyId);
    }

    public function refresh(EmailThread $thread, int $companyId): AiTriageResult
    {
        $config = $this->configService->resolve();
        $analysis = $this->triageSkill->analyze($thread, $config);

        $attributes = [
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
        ];

        $values = array_merge($analysis, [
            'model_version' => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'analyzed_at' => now(),
        ]);

        AiTriageResult::query()->updateOrCreate($attributes, $values);

        return AiTriageResult::query()
            ->where('created_by', $companyId)
            ->where('email_thread_id', $thread->id)
            ->firstOrFail();
    }
}
