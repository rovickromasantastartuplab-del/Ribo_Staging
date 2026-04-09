<?php

namespace App\Services\AI;

use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\Contact;
use App\Services\AI\Skills\MemorySkill;

class ConversationAiMemoryService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly MemorySkill $memorySkill
    ) {
    }

    public function show(Contact $contact, int $companyId): array
    {
        $summary = AiMemorySummary::query()
            ->where('created_by', $companyId)
            ->where('contact_id', $contact->id)
            ->latest('id')
            ->first();

        if (!$summary) {
            $summary = $this->generateSummary($contact, $companyId);
        }

        $tasks = AiTask::query()
            ->where('created_by', $companyId)
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->get();

        return [
            'summary' => $summary,
            'tasks' => $tasks,
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

    private function generateSummary(Contact $contact, int $companyId): AiMemorySummary
    {
        $config = $this->configService->resolve();
        $analysis = $this->memorySkill->summarize($contact);

        $data = array_merge($analysis, [
            'created_by' => $companyId,
            'contact_id' => $contact->id,
            'model_version' => (string) ($config['model'] ?? 'gpt-5.4-mini'),
            'summarized_at' => now(),
        ]);

        return AiMemorySummary::query()->create($data);
    }
}
