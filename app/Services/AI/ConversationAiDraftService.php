<?php

namespace App\Services\AI;

use App\Models\AiDraftRun;
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
        $draft = $this->draftSkill->generate($thread, $prompt, $tone, $config);

        $run = AiDraftRun::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
            'prompt' => $prompt,
            'tone' => $tone,
            'subject' => $draft['subject'],
            'body' => $draft['body'],
            'status' => 'completed',
            'model_version' => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'prompt_version' => $draft['prompt_version'],
            'generated_at' => now(),
        ]);

        return [
            'run' => $run,
            'config' => $config,
            'usage' => [
                'prompt_tokens' => (int) ($draft['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($draft['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($draft['total_tokens'] ?? 0),
            ],
        ];
    }
}
