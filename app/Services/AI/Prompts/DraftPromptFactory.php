<?php

namespace App\Services\AI\Prompts;

use App\Models\EmailThread;

class DraftPromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You write clear and context-aware email drafts for conversation follow-ups. Return valid JSON only with keys: subject, body.';
    }

    public function buildUserPrompt(EmailThread $thread, string $instruction, string $tone): string
    {
        $subject = $thread->subject ?: 'No subject';
        $snippet = $thread->snippet ?: 'No snippet';
        $participants = is_array($thread->participants) ? implode(', ', $thread->participants) : 'Unknown participants';
        $history = $this->buildRecentMessageContext($thread);

        return implode("\n", [
            "Thread subject: {$subject}",
            "Thread snippet: {$snippet}",
            "Participants: {$participants}",
            "Recent messages:\n{$history}",
            "Tone: {$tone}",
            "Instruction: {$instruction}",
            'Respond with JSON only: {"subject":"...","body":"<p>...</p>"}',
        ]);
    }

    private function buildRecentMessageContext(EmailThread $thread): string
    {
        $messages = $thread->messages()
            ->orderByDesc('sent_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return '- No historical messages available.';
        }

        return $messages
            ->map(function ($message): string {
                $from = trim((string) ($message->from_email ?? 'unknown'));
                $sentAt = optional($message->sent_at)->toIso8601String() ?? 'unknown time';
                $content = strip_tags((string) ($message->body_html ?? $message->body_preview ?? ''));
                $content = preg_replace('/\s+/', ' ', $content ?? '') ?? '';
                $content = trim($content);
                if ($content === '') {
                    $content = '(no message text)';
                }
                $content = mb_substr($content, 0, 400);

                return "- {$sentAt} {$from}: {$content}";
            })
            ->implode("\n");
    }
}
