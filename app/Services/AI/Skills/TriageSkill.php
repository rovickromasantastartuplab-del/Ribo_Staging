<?php

namespace App\Services\AI\Skills;

use App\Models\EmailThread;
use App\Services\AI\Prompts\TriagePromptFactory;

class TriageSkill
{
    public function __construct(
        private readonly TriagePromptFactory $promptFactory
    ) {
    }

    public function analyze(EmailThread $thread): array
    {
        $this->promptFactory->buildSystemPrompt();
        $this->promptFactory->buildUserPrompt($thread);

        $subject = trim((string) $thread->subject);
        $summary = $subject !== ''
            ? "Thread requires attention: {$subject}."
            : 'Thread requires attention.';

        $priority = $thread->priority ? strtolower($thread->priority) : 'medium';

        return [
            'intent' => 'follow_up',
            'intent_confidence' => 68,
            'priority' => $priority,
            'success_probability' => 55,
            'behavioral_pulse' => 'neutral',
            'summary' => $summary,
            'strategic_action_json' => [
                'goal' => 'advance_conversation',
                'reason' => 'customer_activity_detected',
                'recommendation' => 'send a concise follow-up response',
            ],
            'prompt_version' => 'triage-v1',
        ];
    }
}
