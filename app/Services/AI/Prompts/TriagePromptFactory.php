<?php

namespace App\Services\AI\Prompts;

use App\Models\EmailThread;

class TriagePromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You triage conversation threads. Return JSON only with keys: summary, intent, intent_confidence, priority, success_probability, behavioral_pulse, strategic_action.';
    }

    public function buildUserPrompt(EmailThread $thread): string
    {
        $subject = $thread->subject ?? 'No subject';
        $snippet = $thread->snippet ?? 'No snippet';
        $participants = is_array($thread->participants) ? implode(', ', $thread->participants) : 'Unknown participants';
        $history = $this->buildRecentMessageContext($thread);

        return implode("\n", [
            "Subject: {$subject}",
            "Snippet: {$snippet}",
            "Participants: {$participants}",
            "Thread status: " . ($thread->status ?? 'Unknown'),
            "Thread priority: " . strtolower((string) ($thread->priority ?? '')),
            "Recent messages:\n{$history}",
            'Use enums: intent in [sales,support,billing,partnership,spam,general,follow_up], priority in [low,medium,high,urgent], behavioral_pulse in [heating_up,cooling_down,stable].',
            'strategic_action must include goal, reason, recommendation.',
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
                $content = mb_substr($content, 0, 300);

                return "- {$sentAt} {$from}: {$content}";
            })
            ->implode("\n");
    }
}
