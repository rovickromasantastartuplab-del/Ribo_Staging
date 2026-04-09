<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\EmailThread;
use App\Services\AI\ConversationAiDraftService;
use App\Services\AI\ConversationAiTelemetryService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversationAiDraftController extends Controller
{
    public function __construct(
        private readonly ConversationAiDraftService $draftService,
        private readonly ConversationAiRules $rules,
        private readonly ConversationAiTelemetryService $telemetryService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threadId' => ['required', 'integer'],
            'prompt' => ['required', 'string'],
            'tone' => ['nullable', 'string', 'max:50'],
        ]);

        $companyId = (int) auth()->user()->creatorId();
        $thread = EmailThread::query()
            ->where('id', (int) $validated['threadId'])
            ->where('created_by', $companyId)
            ->firstOrFail();

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure(
                $companyId,
                'draft',
                $thread->id,
                (string) ($config['model'] ?? null),
                ['reason' => 'unavailable']
            );

            return response()->json(['message' => 'AI unavailable'], 422);
        }

        try {
            $result = $this->draftService->generate(
                $thread,
                $companyId,
                $validated['prompt'],
                (string) ($validated['tone'] ?? 'professional')
            );
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure(
                $companyId,
                'draft',
                $thread->id,
                (string) ($config['model'] ?? null),
                ['reason' => 'provider_failure']
            );

            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $run = $result['run'];
        $usage = $result['usage'];

        $this->telemetryService->recordSuccess(
            $companyId,
            'draft',
            $thread->id,
            (string) ($run->model_version ?? ($config['model'] ?? null)),
            ['tone' => (string) ($validated['tone'] ?? 'professional')],
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            (int) ($usage['total_tokens'] ?? 0)
        );

        return response()->json([
            'data' => [
                'id' => $run->id,
                'subject' => $run->subject,
                'body' => $run->body,
                'generated_at' => optional($run->generated_at)->toIso8601String(),
            ],
        ]);
    }
}
