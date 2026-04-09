<?php

namespace App\Services\AI;

use App\Models\AiDraftRun;
use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Skills\DraftSkill;

class ConversationAiDraftService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly DraftSkill $draftSkill
    ) {
    }

    public function generate(EmailThread $thread, int $companyId, string $prompt, string $tone): array
    {
        $config = $this->configService->resolve();

        // Load the latest triage result so DraftSkill can enforce state-aware guards
        $triage = AiTriageResult::query()
            ->where('email_thread_id', $thread->id)
            ->where('created_by', $companyId)
            ->latest('analyzed_at')
            ->first();

        $analysis = $this->draftSkill->generate($thread, $prompt, $tone, $config, $triage);
        $draft    = $analysis['result'];
        $metadata = $analysis['metadata'] ?? [];

        // Triage guard blocked the draft — do not write to DB, surface a clean signal
        $fallbackReason = $metadata['fallback_reason'] ?? '';
        if ($metadata['fallback_applied'] && str_starts_with($fallbackReason, 'draft_blocked')) {
            throw new \RuntimeException('DRAFT_BLOCKED:' . $fallbackReason);
        }

        $run = AiDraftRun::query()->create([
            'created_by'      => $companyId,
            'email_thread_id' => $thread->id,
            'prompt'          => $prompt,
            'tone'            => $tone,
            'subject'         => $draft['subject'],
            'body'            => $draft['body'],
            'status'          => 'completed',
            'model_version'   => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'prompt_version'  => $draft['prompt_version'] ?? 'unknown',
            'generated_at'    => now(),
        ]);

        return [
            'run' => $run,
            'config' => $config,
            'metadata' => $metadata,
            'usage' => [
                'prompt_tokens' => (int) ($draft['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($draft['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($draft['total_tokens'] ?? 0),
            ],
        ];
    }
}
