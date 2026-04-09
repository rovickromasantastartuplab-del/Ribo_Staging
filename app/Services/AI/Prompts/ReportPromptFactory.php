<?php

namespace App\Services\AI\Prompts;

use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;

class ReportPromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You generate concise conversation reports. Return JSON only with keys: summary, key_insights, next_actions.';
    }

    public function buildUserPrompt(AiReportJob $job): string
    {
        $scope = $job->scope ?: 'overall';
        $threadId = $job->email_thread_id ?: 'none';
        $contactId = $job->contact_id ?: 'none';
        $thread = $job->email_thread_id ? EmailThread::query()->find($job->email_thread_id) : null;
        $contact = $job->contact_id ? Contact::query()->find($job->contact_id) : null;

        $threadSubject = $thread?->subject ?: 'No subject';
        $threadSnippet = $thread?->snippet ?: 'No snippet';
        $contactName = $contact?->name ?: 'N/A';
        $contactEmail = $contact?->email ?: 'N/A';

        $messagesSummary = '- No messages available.';
        if ($thread) {
            $messages = $thread->messages()
                ->orderByDesc('sent_at')
                ->limit(10)
                ->get()
                ->reverse()
                ->values();
            if ($messages->isNotEmpty()) {
                $messagesSummary = $messages->map(function ($message): string {
                    $sentAt = optional($message->sent_at)->toIso8601String() ?? 'unknown time';
                    $from = trim((string) ($message->from_email ?? 'unknown'));
                    $content = strip_tags((string) ($message->body_html ?? $message->body_preview ?? ''));
                    $content = preg_replace('/\s+/', ' ', $content ?? '') ?? '';
                    $content = mb_substr(trim($content), 0, 260);
                    return "- {$sentAt} {$from}: " . ($content !== '' ? $content : '(no message text)');
                })->implode("\n");
            }
        }

        return implode("\n", [
            "Scope: {$scope}",
            "Thread ID: {$threadId}",
            "Thread Subject: {$threadSubject}",
            "Thread Snippet: {$threadSnippet}",
            "Contact ID: {$contactId}",
            "Contact Name: {$contactName}",
            "Contact Email: {$contactEmail}",
            "Recent messages:\n{$messagesSummary}",
        ]);
    }
}
