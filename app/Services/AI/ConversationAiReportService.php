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
        private readonly ReportSkill $reportSkill
    ) {
    }

    public function queue(
        int $companyId,
        EmailThread $thread,
        string $scope,
        ?Contact $contact
    ): AiReportJob {
        $payload = [
            'threadId' => $thread->id,
            'scope' => $scope,
            'contactId' => $contact?->id,
        ];

        $job = AiReportJob::query()->create([
            'created_by' => $companyId,
            'email_thread_id' => $thread->id,
            'contact_id' => $contact?->id,
            'scope' => $scope,
            'status' => 'queued',
            'request_payload_json' => $payload,
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

    public function process(AiReportJob $job, int $companyId): AiReportJob
    {
        $config = $this->configService->resolve();

        // Load latest triage for this thread so Report reflects the same state
        $triage = $job->email_thread_id
            ? AiTriageResult::query()
                ->where('email_thread_id', $job->email_thread_id)
                ->where('created_by', $companyId)
                ->latest('analyzed_at')
                ->first()
            : null;

        try {
            $analysis = $this->reportSkill->generate($job, $config, $triage);
            $report   = $analysis['result'];
            $metadata = $analysis['metadata'] ?? [];

            // Store triage snapshot alongside report payload for historical accuracy
            $payload = array_merge($report, array_filter([
                'thread_state'        => $triage?->thread_state,
                'relationship_health' => $triage?->relationship_health,
                'actionability'       => $triage?->actionability,
                'success_probability' => $triage?->success_probability,
            ], fn($v) => $v !== null));

            $job->update([
                'status'              => ($metadata['fallback_applied'] ?? false) ? 'fallback' : 'completed',
                'result_payload_json' => $payload,
                'metadata_json'       => $metadata,
                'completed_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
