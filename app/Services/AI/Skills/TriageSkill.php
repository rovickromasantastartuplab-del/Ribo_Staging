<?php

namespace App\Services\AI\Skills;

use App\Models\EmailThread;
use App\Services\AI\Prompts\TriagePromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class TriageSkill
{
    public function __construct(
        private readonly TriagePromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function analyze(EmailThread $thread, array $config): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($thread);

        return $this->provider->analyzeTriage($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'thread_subject' => $thread->subject,
        ]);
    }
}
