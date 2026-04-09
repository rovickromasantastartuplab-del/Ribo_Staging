<?php

namespace App\Services\AI\Prompts;

use App\Models\Contact;

class MemoryPromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You summarize customer relationship memory and suggest actionable follow-up tasks.';
    }

    public function buildUserPrompt(Contact $contact): string
    {
        $name = $contact->name ?: 'Unknown contact';
        $email = $contact->email ?: 'Unknown email';

        return "Contact: {$name}\nEmail: {$email}";
    }
}
