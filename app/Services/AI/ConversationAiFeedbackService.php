<?php

namespace App\Services\AI;

use App\Models\AiFeedbackLog;
use App\Models\AiTask;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\EmailThread;

class ConversationAiFeedbackService
{
    public function log(
        int $companyId,
        EmailThread $thread,
        ?AiTriageResult $triageResult,
        ?Contact $contact,
        ?AiTask $task,
        ?string $errorType,
        ?string $notes,
        ?array $payload = null
    ): AiFeedbackLog {
        $metadata = array_filter([
            'contact_id' => $contact?->id,
            'task_id' => $task?->id,
            'thread_id' => $thread->id,
            'triage_result_id' => $triageResult?->id,
        ], static fn ($value) => $value !== null);

        $payloadJson = array_merge($metadata, $payload ?? []);

        return AiFeedbackLog::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
            'ai_triage_result_id' => $triageResult?->id,
            'error_type' => $errorType,
            'notes' => $notes,
            'payload_json' => $payloadJson,
            'logged_at' => now(),
        ]);
    }
}
