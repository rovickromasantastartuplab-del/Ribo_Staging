<?php

namespace App\Services\AI\Prompts;

use App\Models\EmailThread;

class TriagePromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You are an assistant that triages conversation threads into intent, priority, and short summaries.';
    }

    public function buildUserPrompt(EmailThread $thread): string
    {
        $subject = $thread->subject ?? 'No subject';
        $snippet = $thread->snippet ?? 'No snippet';

        return "Subject: {$subject}\nSnippet: {$snippet}";
    }
}
