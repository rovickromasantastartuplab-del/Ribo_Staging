<?php

namespace App\Services\AI;

use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\AI\Skills\MemorySkill;

class ConversationAiMemoryService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly MemorySkill $memorySkill
    ) {
    }

    public function show(Contact|Lead $entity, int $companyId): array
    {
        $entityType = $entity instanceof Contact ? 'contact' : 'lead';
        $column = $entityType . '_id';

        $telemetryMetadata = [];
        $summary = AiMemorySummary::query()
            ->where('created_by', $companyId)
            ->where($column, $entity->id)
            ->latest('id')
            ->first();

        if ($summary) {
            // Auto-refresh if the entity has new activity since the last summary (with 5s safety buffer)
            $latestActivity = $entity instanceof Contact ? $entity->last_inbound_at : $entity->last_activity_at;
            $latestThread = $entity->emailThreads()->first();
            if ($latestThread && (!$latestActivity || $latestThread->last_message_at?->isAfter($latestActivity))) {
                $latestActivity = $latestThread->last_message_at;
            }

            if ($latestActivity && $summary->summarized_at && $latestActivity->addSeconds(5)->isAfter($summary->summarized_at)) {
                $generated = $this->generateSummary($entity, $companyId);
                $summary = $generated['summary'];
                $telemetryMetadata = $generated['metadata'];
            } else {
                $telemetryMetadata = [
                    'prompt_version' => (string) ($summary->prompt_version ?? ''),
                    'source' => 'cached',
                ];
            }
        } else {
            $generated = $this->generateSummary($entity, $companyId);
            $summary = $generated['summary'];
            $telemetryMetadata = $generated['metadata'];
        }

        $tasks = AiTask::query()
            ->where('created_by', $companyId)
            ->where($column, $entity->id)
            ->orderByDesc('id')
            ->get();

        return [
            'summary' => $summary,
            'tasks' => $tasks,
            'metadata' => $telemetryMetadata,
        ];
    }

    public function updateTask(AiTask $task, int $companyId, bool $isCompleted): AiTask
    {
        $payload = [
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
        ];

        AiTask::query()
            ->where('id', $task->id)
            ->where('created_by', $companyId)
            ->update($payload);

        return AiTask::query()
            ->where('id', $task->id)
            ->where('created_by', $companyId)
            ->firstOrFail();
    }

    private function generateSummary(Contact|Lead $entity, int $companyId): array
    {
        $config = $this->configService->resolve();
        $entityType = $entity instanceof Contact ? 'contact' : 'lead';

        // Build triage context from the entity's recent threads
        $recentThreadIds = $entity->emailThreads()
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->pluck('email_threads.id');

        $triageResults = AiTriageResult::query()
            ->whereIn('email_thread_id', $recentThreadIds)
            ->where('created_by', $companyId)
            ->latest('analyzed_at')
            ->get()
            ->keyBy('email_thread_id');

        $triageContext = [];
        foreach ($recentThreadIds as $index => $threadId) {
            $triage = $triageResults->get($threadId);
            if ($triage) {
                $triageContext[] = [
                    'thread_id'           => $threadId,
                    'thread_state'         => $triage->thread_state,
                    'relationship_health'  => $triage->relationship_health,
                    'behavioral_pulse'     => $triage->behavioral_pulse,
                    'success_probability'  => $triage->success_probability,
                    'is_latest'            => $index === 0,
                ];
            }
        }

        $analysis = $this->memorySkill->summarize($entity, $config, $triageContext);
        $result   = $analysis['result'];
        $metadata = $analysis['metadata'] ?? [];

        $data = array_merge($result, [
            'created_by' => $companyId,
            $entityType . '_id' => $entity->id,
            'model_version' => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'summarized_at' => now(),
        ]);

        return [
            'summary' => AiMemorySummary::query()->create($data),
            'metadata' => $metadata,
        ];
    }
}
