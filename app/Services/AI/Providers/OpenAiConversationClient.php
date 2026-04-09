<?php

namespace App\Services\AI\Providers;

use RuntimeException;

class OpenAiConversationClient
{
    public function generateDraft(array $config, array $payload): array
    {
        $enabled = (bool) ($config['enabled'] ?? false);
        $apiKey = (string) ($config['api_key'] ?? '');

        if (!$enabled || $apiKey === '') {
            throw new RuntimeException('AI unavailable');
        }

        if (str_starts_with($apiKey, 'fail-')) {
            throw new RuntimeException("Upstream provider error for key {$apiKey}");
        }

        $threadSubject = trim((string) ($payload['thread_subject'] ?? ''));
        $instruction = (string) ($payload['instruction'] ?? 'Write a follow-up');
        $subject = $threadSubject !== '' ? "Re: {$threadSubject}" : 'Follow-up';

        return [
            'subject' => $subject,
            'body' => "Hi,\n\n{$instruction}\n\nBest regards,",
            'prompt_version' => 'draft-v1',
            'prompt_tokens' => 20,
            'completion_tokens' => 35,
            'total_tokens' => 55,
        ];
    }
}
