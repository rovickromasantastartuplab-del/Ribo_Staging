<?php

namespace App\Http\Controllers\AI;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\AiReportJob;
use App\Models\AiReportVersion;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Reports\ReportTemplateFormatter;
use App\Services\AI\ConversationAiReportService;
use App\Services\AI\ConversationAiTelemetryService;
use App\Services\AI\ConversationAiReportVersionService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationAiReportController extends Controller
{
    public function __construct(
        private readonly ConversationAiReportService $reportService,
        private readonly ConversationAiRules $rules,
        private readonly ConversationAiTelemetryService $telemetryService,
        private readonly ReportTemplateFormatter $reportTemplateFormatter,
        private readonly ConversationAiReportVersionService $reportVersionService
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

        $scopeOptions = $this->reportService->scopeOptions($companyId, $thread);

        $contact = null;
        if (!empty($validated['contactId'])) {
            $contact = Contact::query()
                ->where('id', (int) $validated['contactId'])
                ->where('created_by', $companyId)
                ->firstOrFail();

            $allowedContact = collect($scopeOptions['contacts'] ?? [])
                ->pluck('id')
                ->contains((int) $contact->id);

            if (!$allowedContact) {
                return response()->json([
                    'message' => 'Selected contact is not linked to this report context.',
                    'errors' => ['contactId' => ['Invalid contact selection for this thread.']],
                ], 422);
            }
        }

        $selectedOpportunityId = isset($validated['opportunityId']) ? (int) $validated['opportunityId'] : null;
        if (($validated['scope'] ?? null) === 'specific-opportunity' && $selectedOpportunityId !== null) {
            $allowed = collect($scopeOptions['opportunities'] ?? [])
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

            // Sync mode: process inline and return final status immediately.
            $this->reportService->process($job);
            $job->refresh();

            if ($job->status === 'failed') {
                $this->telemetryService->recordFailure(
                    $companyId,
                    'report_generate',
                    $thread->id,
                    (string) ($config['model'] ?? null),
                    [
                        'scope' => (string) ($validated['scope'] ?? 'overall'),
                        'phase' => 'completed_sync',
                        'reason' => 'process_failed',
                    ]
                );

                return response()->json(['message' => 'AI unavailable'], 422);
            }

        } catch (Throwable $e) {
            $this->telemetryService->recordFailure($companyId, 'report_generate', $thread->id, (string) ($config['model'] ?? null), ['reason' => 'provider_failure']);
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $this->telemetryService->recordSuccess(
            $companyId,
            'report_generate',
            $thread->id,
            (string) ($config['model'] ?? null),
            array_merge([
                'scope' => (string) ($validated['scope'] ?? 'overall'),
                'prompt_version' => ReportPromptFactory::VERSION,
                'phase' => 'completed_sync',
            ], (array) ($job->metadata_json ?? [])),
            (int) (($job->metadata_json['prompt_tokens'] ?? 0)),
            (int) (($job->metadata_json['completion_tokens'] ?? 0)),
            (int) (($job->metadata_json['total_tokens'] ?? 0))
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
            $pdfContent = $this->buildReportPdfBinary($reportJob);
            if ($pdfContent === null) {
                return response()->json([
                    'message' => 'Report result unavailable',
                    'code' => 'report_result_unavailable',
                ], 409);
            }

            $this->reportVersionService->recordSuccessfulDownload(
                $reportJob,
                (int) auth()->id(),
                $pdfContent,
                'ai-summary-v1'
            );

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=AI-Summary-Report-{$reportJob->id}.pdf",
            ]);
        } catch (Throwable $e) {
            Log::error('[ConversationAiReportController] Download failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate summary report. Please try again.',
            ], 500);
        }
    }

    public function history(EmailThread $thread): JsonResponse
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $thread->created_by !== $companyId, 403);

        $rows = AiReportVersion::query()
            ->where('created_by', $companyId)
            ->where('email_thread_id', $thread->id)
            ->with(['lastDownloader:id,name'])
            ->orderByDesc('last_downloaded_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(static fn (AiReportVersion $row): array => [
                'id' => $row->id,
                'ai_report_job_id' => $row->ai_report_job_id,
                'scope' => $row->scope,
                'download_count' => $row->download_count,
                'first_downloaded_at' => optional($row->first_downloaded_at)->toIso8601String(),
                'last_downloaded_at' => optional($row->last_downloaded_at)->toIso8601String(),
                'last_downloaded_by' => $row->lastDownloader ? [
                    'id' => $row->lastDownloader->id,
                    'name' => $row->lastDownloader->name,
                ] : null,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function downloadVersion(AiReportVersion $version)
    {
        $companyId = (int) auth()->user()->creatorId();
        abort_if((int) $version->created_by !== $companyId, 403);

        try {
            $reportJob = $this->reportService->get(
                AiReportJob::query()->findOrFail($version->ai_report_job_id),
                $companyId
            );

            $pdfContent = $this->reportVersionService->getOrRestorePdf(
                $version,
                function () use ($reportJob, $version): string {
                    $snapshot = is_array($version->snapshot_json) ? $version->snapshot_json : [];
                    $regenerated = $this->buildReportPdfBinary($reportJob, $snapshot);
                    if ($regenerated === null) {
                        throw new \RuntimeException('report_result_unavailable');
                    }

                    return $regenerated;
                }
            );

            $this->reportVersionService->markRedownload($version, (int) auth()->id());

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=AI-Summary-Report-{$reportJob->id}.pdf",
            ]);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'report_result_unavailable') {
                return response()->json([
                    'message' => 'Report result unavailable',
                    'code' => 'report_result_unavailable',
                ], 409);
            }

            Log::error('[ConversationAiReportController] Version download failed', [
                'version_id' => $version->id,
                'job_id' => $version->ai_report_job_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate summary report. Please try again.',
            ], 500);
        }
    }

    private function buildReportPdfBinary(AiReportJob $reportJob, array $snapshot = []): ?string
    {
        $result = $reportJob->result_payload_json ?? ($snapshot['result'] ?? null);
        if (!is_array($result) || empty($result)) {
            return null;
        }

        $context = $reportJob->context_payload_json ?? ($snapshot['context'] ?? []);
        if (!is_array($context)) {
            $context = [];
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

        return $pdf->output();
    }
}
