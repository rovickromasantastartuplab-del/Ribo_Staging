<?php

namespace App\Services\AI;

use App\Models\AiTask;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\Lead;
use Carbon\Carbon;

class OpenLoopTaskService
{
    public function __construct(
        private readonly OpenLoopExtractor $extractor
    ) {
    }

    public function upsertFromThread(EmailThread $thread, int $companyId): void
    {
        $candidates = $this->extractor->extractFromThread($thread);
        if (empty($candidates)) {
            return;
        }

        $contacts = $thread->contacts()->select('contacts.id')->get();
        $leads = $thread->leads()->select('leads.id')->get();

        foreach ($contacts as $contact) {
            foreach ($candidates as $candidate) {
                $this->upsertCandidate($companyId, ['contact_id' => (int) $contact->id], $thread->id, $candidate);
            }
        }

        foreach ($leads as $lead) {
            foreach ($candidates as $candidate) {
                $this->upsertCandidate($companyId, ['lead_id' => (int) $lead->id], $thread->id, $candidate);
            }
        }
    }

    public function reconcileEntity(Contact|Lead $entity, int $companyId): void
    {
        $threads = $entity->emailThreads()
            ->where('email_threads.created_by', $companyId)
            ->limit(5)
            ->get();

        if ($threads->isEmpty()) {
            return;
        }

        $entityPayload = $entity instanceof Contact
            ? ['contact_id' => (int) $entity->id]
            : ['lead_id' => (int) $entity->id];

        foreach ($threads as $thread) {
            $candidates = $this->extractor->extractFromThread($thread);
            foreach ($candidates as $candidate) {
                $this->upsertCandidate($companyId, $entityPayload, $thread->id, $candidate);
            }
        }
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, int> $entityPayload
     */
    private function upsertCandidate(int $companyId, array $entityPayload, int $threadId, array $candidate): void
    {
        $existingTask = $this->findExistingTask($companyId, $entityPayload, (string) $candidate['loop_key']);
        $metadata = $this->mergeMetadata($existingTask?->metadata_json, $candidate, $threadId);

        if (!$existingTask) {
            AiTask::query()->create(array_merge($entityPayload, [
                'created_by' => $companyId,
                'email_thread_id' => $threadId,
                'title' => (string) $candidate['title'],
                'priority' => 'medium',
                'is_completed' => false,
                'source' => 'ai',
                'metadata_json' => $metadata,
            ]));

            return;
        }

        $payload = [
            'email_thread_id' => $threadId,
            'metadata_json' => $metadata,
        ];

        if (
            $existingTask->is_completed
            && (string) ($candidate['confidence'] ?? 'weak') === 'strong'
            && $this->canReopenTask($existingTask, (string) ($candidate['detected_at'] ?? ''))
        ) {
            $metadata['reopened_count'] = (int) ($metadata['reopened_count'] ?? 0) + 1;
            $metadata['last_reopened_at'] = now()->toIso8601String();

            $payload['is_completed'] = false;
            $payload['completed_at'] = null;
            $payload['title'] = (string) $candidate['title'];
            $payload['metadata_json'] = $metadata;
        }

        $existingTask->update($payload);
    }

    /**
     * @param array<string, int> $entityPayload
     */
    private function findExistingTask(int $companyId, array $entityPayload, string $loopKey): ?AiTask
    {
        $tasks = AiTask::query()
            ->where('created_by', $companyId)
            ->where($entityPayload)
            ->where('source', 'ai')
            ->orderByDesc('id')
            ->get();

        foreach ($tasks as $task) {
            $taskLoopKey = $this->extractor->normalizeLoopKey((string) data_get($task->metadata_json, 'loop_key', $task->title));
            if ($taskLoopKey === $loopKey) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function mergeMetadata(?array $existing, array $candidate, int $threadId): array
    {
        $metadata = $existing ?? [];
        $metadata['loop_key'] = (string) ($candidate['loop_key'] ?? '');
        $metadata['evidence_hash'] = (string) ($candidate['evidence_hash'] ?? '');
        $metadata['evidence_thread_id'] = $threadId;
        $metadata['last_detected_at'] = (string) ($candidate['detected_at'] ?? now()->toIso8601String());

        if (($candidate['confidence'] ?? 'weak') === 'strong') {
            $metadata['last_strong_detected_at'] = (string) ($candidate['detected_at'] ?? now()->toIso8601String());
        }

        if (!isset($metadata['reopened_count'])) {
            $metadata['reopened_count'] = 0;
        }

        return $metadata;
    }

    private function canReopenTask(AiTask $task, string $detectedAt): bool
    {
        if (!$task->completed_at) {
            return true;
        }

        try {
            $detected = Carbon::parse($detectedAt);
        } catch (\Throwable) {
            return false;
        }

        return $detected->isAfter($task->completed_at);
    }
}
