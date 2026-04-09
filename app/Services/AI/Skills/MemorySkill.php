<?php

namespace App\Services\AI\Skills;

use App\Models\Contact;
use App\Services\AI\Prompts\MemoryPromptFactory;

class MemorySkill
{
    public function __construct(
        private readonly MemoryPromptFactory $promptFactory
    ) {
    }

    public function summarize(Contact $contact): array
    {
        $this->promptFactory->buildSystemPrompt();
        $this->promptFactory->buildUserPrompt($contact);

        return [
            'relationship_summary' => "Recent interactions indicate ongoing collaboration with {$contact->name}.",
            'relationship_strength' => 'moderate',
            'memory_points_json' => [
                'follow_up_requested',
                'maintain_weekly_touchpoint',
            ],
            'prompt_version' => 'memory-v1',
        ];
    }
}
