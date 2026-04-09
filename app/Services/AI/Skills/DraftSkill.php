<?php

namespace App\Services\AI\Skills;

use App\Models\EmailThread;
use App\Services\AI\Prompts\DraftPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class DraftSkill
{
    public function __construct(
        private readonly DraftPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function generate(EmailThread $thread, string $instruction, string $tone, array $config): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($thread, $instruction, $tone);

        return $this->provider->generateDraft($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'thread_subject' => $thread->subject,
            'instruction' => $instruction,
            'tone' => $tone,
        ]);
    }
}
