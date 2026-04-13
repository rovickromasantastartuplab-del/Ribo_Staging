<?php

namespace Tests\Unit\AI;

use App\Models\AiReportJob;
use App\Models\AiTriageResult;
use App\Services\AI\ConversationAiConfigService;
use App\Services\AI\ConversationAiReportContextBuilder;
use App\Services\AI\ConversationAiReportService;
use App\Services\AI\Skills\ReportSkill;
use Mockery;
use Tests\TestCase;

class ConversationAiReportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_processes_a_report_job_id_and_persists_triage_snapshot_fields(): void
    {
        $job = Mockery::mock(new AiReportJob([
            'created_by' => 44,
            'email_thread_id' => 91,
        ]))->makePartial();
        $job->shouldReceive('update')->once()->withArgs(function (array $payload): bool {
            return ($payload['status'] ?? null) === 'completed'
                && ($payload['result_payload_json']['thread_state'] ?? null) === 'misaligned'
                && ($payload['result_payload_json']['relationship_health'] ?? null) === 'strained'
                && ($payload['result_payload_json']['actionability'] ?? null) === 'act_now'
                && ($payload['result_payload_json']['behavioral_pulse'] ?? null) === 'cooling_down'
                && ($payload['result_payload_json']['success_probability'] ?? null) === 18;
        });

        $triage = new AiTriageResult([
            'thread_state' => 'misaligned',
            'relationship_health' => 'strained',
            'actionability' => 'act_now',
            'behavioral_pulse' => 'cooling_down',
            'success_probability' => 18,
            'summary' => 'The customer says our rollout process does not fit their approval path.',
            'strategic_action_json' => [
                'goal' => 'Repair process mismatch',
                'reason' => 'The prospect rejected the current buying process.',
                'recommendation' => 'Clarify the process mismatch before proposing another live review.',
            ],
        ]);

        $configService = Mockery::mock(ConversationAiConfigService::class);
        $configService->shouldReceive('resolve')->once()->andReturn([
            'enabled' => true,
            'api_key' => 'test-key',
            'model' => 'gpt-5.4-mini',
            'timeout_seconds' => 30,
        ]);

        $reportSkill = Mockery::mock(ReportSkill::class);
        $reportSkill->shouldReceive('generate')->once()->with(
            $job,
            Mockery::type('array'),
            $triage
        )->andReturn([
            'result' => [
                'summary' => '[MISALIGNED] Leadership should treat this as a process-fit issue, not active momentum.',
                'key_insights' => ['The thread shifted into process misalignment.'],
                'next_actions' => ['Clarify the process mismatch internally before any customer-facing push.'],
                'prompt_version' => 'v2-expert-chief-of-staff',
            ],
            'metadata' => [
                'prompt_version' => 'v2-expert-chief-of-staff',
                'validation_stage_failed' => null,
                'repair_applied' => false,
                'repair_type' => null,
                'fallback_applied' => false,
                'fallback_reason' => null,
            ],
        ]);

        $contextBuilder = Mockery::mock(ConversationAiReportContextBuilder::class);

        $service = Mockery::mock(ConversationAiReportService::class, [$configService, $reportSkill, $contextBuilder])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('resolveReportJob')->once()->with(123)->andReturn($job);
        $service->shouldReceive('resolveTriageSnapshot')->once()->with($job)->andReturn($triage);

        $service->process(123);

        $this->assertTrue(true);
    }
}
