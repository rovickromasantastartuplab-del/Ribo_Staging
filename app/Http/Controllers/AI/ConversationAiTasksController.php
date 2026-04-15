<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTask;
use App\Services\AI\ConversationAiMemoryService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationAiTasksController extends Controller
{
    public function __construct(
        private readonly ConversationAiMemoryService $memoryService,
        private readonly ConversationAiRules $rules
    ) {
    }

    public function update(Request $request, AiTask $task): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $task->created_by !== $companyId, 403);

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $validated = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        $updatedTask = $this->memoryService->updateTask(
            $task,
            $companyId,
            (bool) $validated['is_completed']
        );

        return response()->json([
            'data' => [
                'id' => $updatedTask->id,
                'is_completed' => $updatedTask->is_completed,
                'completed_at' => optional($updatedTask->completed_at)->toIso8601String(),
            ],
        ]);
    }
}
