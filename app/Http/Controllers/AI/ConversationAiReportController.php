<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\ConversationAiReportService;
use App\Services\AI\ConversationAiTelemetryService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversationAiReportController extends Controller
{
    public function __construct(
        private readonly ConversationAiReportService $reportService,
        private readonly ConversationAiRules $rules,
        private readonly ConversationAiTelemetryService $telemetryService
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threadId' => ['required', 'integer'],
            'scope' => ['nullable', 'string', 'max:30'],
            'contactId' => ['nullable', 'integer'],
        ]);

        $companyId = (int) auth()->user()->creatorId();

        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            $this->telemetryService->recordFailure($companyId, 'report_generate', null, (string) ($config['model'] ?? null), ['reason' => 'unavailable']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $thread = EmailThread::query()
            ->where('id', (int) $validated['threadId'])
            ->where('created_by', $companyId)
            ->firstOrFail();

        $contact = null;
        if (!empty($validated['contactId'])) {
            $contact = Contact::query()
                ->where('id', (int) $validated['contactId'])
                ->where('created_by', $companyId)
                ->firstOrFail();
        }

        try {
            $job = $this->reportService->queue(
                $companyId,
                $thread,
                (string) ($validated['scope'] ?? 'overall'),
                $contact
            );
        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'report_generate', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $this->telemetryService->recordSuccess(
            $companyId,
            'report_generate',
            $thread->id,
            (string) ($config['model'] ?? null),
            [
                'scope' => (string) ($validated['scope'] ?? 'overall'),
                'prompt_version' => ReportPromptFactory::VERSION,
                'phase' => 'queued',
            ]
        );

        return response()->json([
            'data' => [
                'job_id' => $job->id,
                'status' => $job->status,
            ],
        ]);
    }

    public function show(AiReportJob $job): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $job->created_by !== $companyId, 403);

        $reportJob = $this->reportService->get($job, $companyId);

        return response()->json([
            'data' => [
                'id' => $reportJob->id,
                'status' => $reportJob->status,
                'scope' => $reportJob->scope,
                'result' => $reportJob->result_payload_json,
                'error_message' => $reportJob->error_message,
                'requested_at' => optional($reportJob->requested_at)->toIso8601String(),
                'completed_at' => optional($reportJob->completed_at)->toIso8601String(),
            ],
        ]);
    }
}
