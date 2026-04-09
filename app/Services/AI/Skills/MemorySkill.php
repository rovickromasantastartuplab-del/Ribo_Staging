<?php

namespace App\Services\AI\Skills;

use App\Models\Contact;
use App\Services\AI\Prompts\MemoryPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class MemorySkill
{
    public function __construct(
        private readonly MemoryPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function summarize(Contact $contact, array $config): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($contact);

        return $this->provider->summarizeMemory($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'contact_name' => $contact->name,
            'contact_email' => $contact->email,
        ]);
    }
}
