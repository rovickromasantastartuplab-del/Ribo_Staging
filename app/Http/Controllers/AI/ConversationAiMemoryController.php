<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\AI\ConversationAiMemoryService;
use Illuminate\Http\Request;
use App\Services\AI\ConversationAiTelemetryService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Throwable;

class ConversationAiMemoryController extends Controller
{
    public function __construct(
        private readonly ConversationAiMemoryService $memoryService,
        private readonly ConversationAiRules $rules,
        private readonly ConversationAiTelemetryService $telemetryService
    ) {
    }

    public function show($id, Request $request): JsonResponse
    {
        $type = $request->query('entity_type', 'contact');
        $companyId = (int) auth()->user()->creatorId();

        if ($type === 'lead') {
            $entity = Lead::findOrFail($id);
        } else {
            $entity = Contact::findOrFail($id);
        }

        abort_if((int) $entity->created_by !== $companyId, 403);

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure($companyId, 'memory_show', null, (string) ($config['model'] ?? null), ['reason' => 'unavailable']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        try {
            $payload = $this->memoryService->show($entity, $companyId);
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'memory_show', null, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $summary = $payload['summary'];
        $tasks = $payload['tasks'];
        $metadata = $payload['metadata'] ?? [];

        $this->telemetryService->recordSuccess(
            $companyId,
            'memory_show',
            null,
            (string) ($summary->model_version ?? ($config['model'] ?? null)),
            array_merge($metadata, [
                'prompt_version' => (string) ($summary->prompt_version ?? ''),
                'relationship_strength' => (string) ($summary->relationship_strength ?? ''),
            ]),
            (int) ($metadata['prompt_tokens'] ?? 0),
            (int) ($metadata['completion_tokens'] ?? 0),
            (int) ($metadata['total_tokens'] ?? 0)
        );

        return response()->json([
            'data' => [
                'entity_id' => $entity->id,
                'entity_type' => $type,
                'contact_id' => $type === 'contact' ? $entity->id : null,
                'lead_id' => $type === 'lead' ? $entity->id : null,
                'relationship_summary' => $summary->relationship_summary,
                'relationship_strength' => $summary->relationship_strength,
                'memory_points' => $summary->memory_points_json,
                'tasks' => $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'is_completed' => $task->is_completed,
                        'due_at' => optional($task->due_at)->toIso8601String(),
                        'completed_at' => optional($task->completed_at)->toIso8601String(),
                    ];
                })->values(),
            ],
        ]);
    }
}
