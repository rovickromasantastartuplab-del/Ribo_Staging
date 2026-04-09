<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\ConversationAiTelemetryService;
use App\Services\AI\ConversationAiTriageService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Throwable;

class ConversationAiTriageController extends Controller
{
    public function __construct(
        private readonly ConversationAiTriageService $triageService,
        private readonly ConversationAiRules $rules,
        private readonly ConversationAiTelemetryService $telemetryService
    ) {
    }

    public function show(EmailThread $thread): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $thread->created_by !== $companyId, 403);

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure($companyId, 'triage_show', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'unavailable']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        try {
            $result = $this->triageService->show($thread, $companyId);
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'triage_show', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        return response()->json([
            'data' => $this->transform($result),
        ]);
    }

    public function refresh(EmailThread $thread): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $thread->created_by !== $companyId, 403);

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure($companyId, 'triage_refresh', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'unavailable']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        try {
            $result = $this->triageService->refresh($thread, $companyId);
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'triage_refresh', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        return response()->json([
            'data' => $this->transform($result),
        ]);
    }

    private function transform(AiTriageResult $result): array
    {
        $statusMap = [
            'active' => 'Open',
            'nurturing' => 'Pending',
            'stalled' => 'Pending',
            'objection' => 'At Risk',
            'misaligned' => 'At Risk',
            'closed_lost' => 'Closed Lost',
            'spam' => 'Ignored',
            'non_commercial' => 'Support/Info',
        ];

        return [
            'id' => $result->id,
            'email_thread_id' => $result->email_thread_id,
            'intent' => $result->intent,
            'intent_confidence' => (int) $result->intent_confidence,
            'priority' => $result->priority,
            'thread_state' => $result->thread_state,
            'relationship_health' => $result->relationship_health,
            'actionability' => $result->actionability,
            'ui_status' => $statusMap[$result->thread_state] ?? 'Pending',
            'success_probability' => (int) $result->success_probability,
            'behavioral_pulse' => $result->behavioral_pulse,
            'summary' => $result->summary,
            'strategic_action' => $result->strategic_action_json,
            'analyzed_at' => optional($result->analyzed_at)->toIso8601String(),
        ];
    }
}
