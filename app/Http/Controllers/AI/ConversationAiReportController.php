<?php

namespace App\Http\Controllers\AI;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\AiReportJob;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Reports\ReportTemplateFormatter;
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
        private readonly ConversationAiTelemetryService $telemetryService,
        private readonly ReportTemplateFormatter $reportTemplateFormatter
    ) {
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threadId' => ['required', 'integer'],
            'scope' => ['nullable', 'string', 'in:overall,leads-only,all-opps,specific-opportunity'],
            'contactId' => ['nullable', 'integer'],
            'opportunityId' => ['nullable', 'integer', 'required_if:scope,specific-opportunity'],
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

        $selectedOpportunityId = isset($validated['opportunityId']) ? (int) $validated['opportunityId'] : null;
        if (($validated['scope'] ?? null) === 'specific-opportunity' && $selectedOpportunityId !== null) {
            $allowed = collect($this->reportService->scopeOptions($companyId, $thread)['opportunities'] ?? [])
                ->pluck('id')
                ->contains($selectedOpportunityId);

            if (!$allowed) {
                return response()->json([
                    'message' => 'Selected opportunity is not linked to this report context.',
                    'errors' => ['opportunityId' => ['Invalid opportunity selection for this thread.']],
                ], 422);
            }
        }

        try {
            $job = $this->reportService->queue(
                $companyId,
                $thread,
                (string) ($validated['scope'] ?? 'overall'),
                $contact,
                $selectedOpportunityId
            );

            // Process synchronously to bypass queue worker requirement on cPanel
            $this->reportService->process($job);
            $job->refresh();

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
                'phase' => 'completed_sync',
            ]
        );

        return response()->json([
            'data' => [
                'job_id' => $job->id,
                'status' => $job->status,
                'result' => $job->result_payload_json,
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

    public function options(EmailThread $thread): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $thread->created_by !== $companyId, 403);

        return response()->json([
            'data' => $this->reportService->scopeOptions($companyId, $thread),
        ]);
    }

    public function download(AiReportJob $job)
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $job->created_by !== $companyId, 403);

        try {
            $reportJob = $this->reportService->get($job, $companyId);
            $result = $reportJob->result_payload_json ?? [];
            $context = $reportJob->context_payload_json ?? [];

            if (empty($result)) {
                return response()->json([
                    'message' => 'Report result unavailable',
                    'code' => 'report_result_unavailable',
                ], 409);
            }

            $formatted = $this->reportTemplateFormatter->format(
                $result,
                $context,
                (string) ($reportJob->scope ?? 'overall')
            );

            $pdf = Pdf::loadView('reports.ai_summary_pdf', [
                'job' => $reportJob,
                'result' => $result,
                'context' => $context,
                'formatted' => $formatted,
            ]);

            return $pdf->download("AI-Summary-Report-{$reportJob->id}.pdf");
        } catch (Throwable $e) {
            Log::error('[ConversationAiReportController] Download failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate summary report. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
