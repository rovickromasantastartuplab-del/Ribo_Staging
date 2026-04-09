<?php

namespace App\Services\AI;

use App\Jobs\AI\GenerateConversationAiReportJob;
use App\Models\AiReportJob;
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

    public function process(int $jobId): void
    {
        $job = AiReportJob::query()->find($jobId);
        if (!$job) {
            return;
        }

        try {
            $result = $this->reportSkill->generate($job, $this->configService->resolve());
            $job->update([
                'status' => 'completed',
                'result_payload_json' => [
                    'summary' => $result['summary'],
                    'key_insights' => $result['key_insights'],
                    'next_actions' => $result['next_actions'],
                    'prompt_version' => $result['prompt_version'] ?? null,
                ],
                'completed_at' => now(),
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
