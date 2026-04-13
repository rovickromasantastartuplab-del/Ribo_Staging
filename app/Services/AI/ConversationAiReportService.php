<?php

namespace App\Services\AI;

use App\Jobs\AI\GenerateConversationAiReportJob;
use App\Models\AiReportJob;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Services\AI\Skills\ReportSkill;

class ConversationAiReportService
{
    public function __construct(
        private readonly ConversationAiConfigService $configService,
        private readonly ReportSkill $reportSkill,
        private readonly ConversationAiReportContextBuilder $contextBuilder
    ) {
    }

    public function queue(
        int $companyId,
        EmailThread $thread,
        string $scope,
        ?Contact $contact,
        ?int $opportunityId = null
    ): AiReportJob {
        $context = $this->contextBuilder->build(
            $companyId,
            $thread,
            $scope,
            $contact,
            $opportunityId
        );

        $payload = [
            'threadId' => $thread->id,
            'scope' => $scope,
            'contactId' => $contact?->id,
            'opportunityId' => $opportunityId,
        ];

        $job = AiReportJob::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
            'contact_id' => $contact?->id,
            'scope' => $scope,
            'status' => 'queued',
            'request_payload_json' => $payload,
            'context_payload_json' => $context,
            'requested_at' => now(),
        ]);

        GenerateConversationAiReportJob::dispatch($job->id);

        return $job;
    }

    public function get(AiReportJob $job, int $companyId): AiReportJob
    {
        return AiReportJob::query()
            ->where('id', $job->id)
            ->where('created_by', $companyId)
            ->firstOrFail();
    }

    public function process(AiReportJob|int $job): AiReportJob
    {
        $job = $this->resolveReportJob($job);
        $config = $this->configService->resolve();
        $triage = $this->resolveTriageSnapshot($job);

        try {
            $analysis = $this->reportSkill->generate($job, $config, $triage);
            $report = $analysis['result'];
            $metadata = $analysis['metadata'] ?? [];

            $payload = array_merge($report, array_filter([
                'thread_state' => $triage?->thread_state,
                'relationship_health' => $triage?->relationship_health,
                'actionability' => $triage?->actionability,
                'behavioral_pulse' => $triage?->behavioral_pulse,
                'success_probability' => $triage?->success_probability,
            ], fn ($value) => $value !== null));

            $job->update([
                'status' => ($metadata['fallback_applied'] ?? false) ? 'fallback' : 'completed',
                'result_payload_json' => $payload,
                'metadata_json' => $metadata,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $job;
    }

    protected function resolveReportJob(AiReportJob|int $job): AiReportJob
    {
        if ($job instanceof AiReportJob) {
            return $job;
        }

        return AiReportJob::query()->findOrFail($job);
    }

    protected function resolveTriageSnapshot(AiReportJob $job): ?AiTriageResult
    {
        if (!$job->email_thread_id || !$job->created_by) {
            return null;
        }

        return AiTriageResult::query()
            ->where('email_thread_id', $job->email_thread_id)
            ->where('created_by', $job->created_by)
            ->latest('analyzed_at')
            ->first();
    }

    public function scopeOptions(int $companyId, EmailThread $thread): array
    {
        return $this->contextBuilder->scopeOptions($companyId, $thread);
    }
}
