<?php

namespace App\Services\AI;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Skills\TriageSkill;

class ConversationAiTriageService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly TriageSkill $triageSkill,
        private readonly ConversationAiTelemetryService $telemetryService
    ) {
    }

    public function show(EmailThread $thread, int $companyId): AiTriageResult
    {
        $existing = AiTriageResult::query()
            ->where('created_by', $companyId)
            ->where('email_thread_id', $thread->id)
            ->first();

        if ($existing) {
            // Auto-refresh if the thread has new activity since the last analysis (with 5s safety buffer)
            if ($thread->last_message_at && $existing->analyzed_at) {
                if ($thread->last_message_at->addSeconds(5)->isAfter($existing->analyzed_at)) {
                    return $this->refresh($thread, $companyId);
                }
            }
            return $existing;
        }

        return $this->refresh($thread, $companyId);
    }

    public function refresh(EmailThread $thread, int $companyId): AiTriageResult
    {
        $config = $this->configService->resolve();

        // Load the previous triage result so TriageSkill can enforce state transitions
        $previousTriage = AiTriageResult::query()
            ->where('created_by', $companyId)
            ->where('email_thread_id', $thread->id)
            ->latest('analyzed_at')
            ->first();

        $analysis = $this->triageSkill->analyze($thread, $config, $previousTriage);

        $attributes = [
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
        ];

        $values = array_merge($analysis['result'], [
            'model_version' => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'analyzed_at' => now(),
        ]);

        $result = AiTriageResult::query()->updateOrCreate($attributes, $values);

        // Log enriched telemetry including validation metadata
        $this->telemetryService->recordSuccess(
            $companyId,
            'triage_refresh',
            $thread->id,
            $result->model_version,
            $analysis['metadata']
        );

        return $result;
    }
}
