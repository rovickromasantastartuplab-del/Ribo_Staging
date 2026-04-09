<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\AI\ConversationAiMemoryService;
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

    public function show(Contact $contact): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $contact->created_by !== $companyId, 403);

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure($companyId, 'memory_show', null, (string) ($config['model'] ?? null), ['reason' => 'unavailable']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        try {
            $payload = $this->memoryService->show($contact, $companyId);
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'memory_show', null, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $summary = $payload['summary'];
        $tasks = $payload['tasks'];

        $this->telemetryService->recordSuccess($companyId, 'memory_show', null, (string) ($summary->model_version ?? ($config['model'] ?? null)));

        return response()->json([
            'data' => [
                'contact_id' => $contact->id,
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
