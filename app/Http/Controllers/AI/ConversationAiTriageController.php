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

        $this->telemetryService->recordSuccess($companyId, 'triage_show', $thread->id, (string) ($result->model_version ?? ($config['model'] ?? null)));

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

        $this->telemetryService->recordSuccess($companyId, 'triage_refresh', $thread->id, (string) ($result->model_version ?? ($config['model'] ?? null)));

        return response()->json([
            'data' => $this->transform($result),
        ]);
    }

    private function transform(AiTriageResult $result): array
    {
        $priority = strtolower((string) ($result->priority ?? ''));
        $suggestedStatus = in_array($priority, ['high', 'urgent'], true) ? 'Open' : 'Pending';

        return [
            'id' => $result->id,
            'email_thread_id' => $result->email_thread_id,
            'intent' => $result->intent,
            'intent_confidence' => $result->intent_confidence,
            'priority' => $result->priority,
            'suggested_status' => $suggestedStatus,
            'success_probability' => $result->success_probability,
            'behavioral_pulse' => $result->behavioral_pulse,
            'summary' => $result->summary,
            'strategic_action' => $result->strategic_action_json,
            'model_version' => $result->model_version,
            'prompt_version' => $result->prompt_version,
            'analyzed_at' => optional($result->analyzed_at)->toIso8601String(),
        ];
    }
}
