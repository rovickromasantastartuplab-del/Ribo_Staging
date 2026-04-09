<?php

namespace App\Services\AI\Prompts;

use App\Models\EmailThread;

class DraftPromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You write clear and context-aware email drafts for conversation follow-ups.';
    }

    public function buildUserPrompt(EmailThread $thread, string $instruction, string $tone): string
    {
        $subject = $thread->subject ?: 'No subject';
        $snippet = $thread->snippet ?: 'No snippet';

        return "Thread subject: {$subject}\nThread snippet: {$snippet}\nTone: {$tone}\nInstruction: {$instruction}";
    }
}
