<?php

namespace Tests\Feature\AI;

use App\Models\AiReportJob;
use App\Models\AiTriageResult;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\ReportSkill;
use Mockery;
use Tests\TestCase;

class ReportValidatorTest extends TestCase
{
    private $mockPromptFactory;
    private $mockJob;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPromptFactory = Mockery::mock(ReportPromptFactory::class);
        $this->mockPromptFactory->shouldReceive('buildSystemPrompt')->andReturn('Report System Prompt');
        $this->mockPromptFactory->shouldReceive('buildUserPrompt')->andReturn('Report User Prompt');
        
        $this->mockJob = Mockery::mock(AiReportJob::class);
        $this->mockJob->shouldReceive('getAttribute')->with('id')->andReturn(789);
        $this->mockJob->shouldReceive('getAttribute')->with('scope')->andReturn('weekly');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_enforces_required_keys_and_falls_back(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary' => 'Missing insights test',
            // 'key_insights' is missing
        ]);

        $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('missing_required_key_key_insights', $response['metadata']['fallback_reason']);
        $this->assertEquals('Manual executive review is recommended due to low confidence report quality.', $response['result']['summary']);
    }

    public function test_it_enforces_valid_report_contract(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary' => 'Invalid contract test',
            'key_insights' => 'Not an array', // Invalid type
            'next_actions' => ['Valid action'],
        ]);

        $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('invalid_report_contract', $response['metadata']['fallback_reason']);
    }

    public function test_it_enforces_non_empty_insights_and_actions(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary' => 'Empty lists test',
            'key_insights' => [], // Empty array
            'next_actions' => ['Valid action'],
        ]);

        $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('empty_insights_or_actions', $response['metadata']['fallback_reason']);
    }

    public function test_it_passes_valid_report(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary' => 'Strong performance this week.',
            'key_insights' => ['Lead volume up 20%', 'Response time decreased'],
            'next_actions' => ['Continue current sequence', 'Optimize demo script'],
        ]);

        $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true]);

        $this->assertFalse($response['metadata']['fallback_applied']);
        $this->assertCount(2, $response['result']['key_insights']);
        $this->assertCount(2, $response['result']['next_actions']);
    }

    public function test_it_prepends_closed_lost_prefix_to_summary(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary'        => 'The customer expressed interest.',
            'key_insights'   => ['Positive signals detected'],
            'next_actions'   => ['Schedule a follow-up call with the prospect'],
            'prompt_version' => 'v1.0-report',
        ]);

        $triage = new AiTriageResult([
            'thread_state'        => 'closed_lost',
            'relationship_health' => 'damaged',
            'actionability'       => 'do_not_pursue',
            'success_probability' => 0,
        ]);

        $skill    = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true], $triage);

        $this->assertStringStartsWith('[CLOSED LOST]', $response['result']['summary']);
    }

    public function test_it_prepends_reopened_prefix_to_summary(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary'        => 'Customer re-engaged after stepping away.',
            'key_insights'   => ['Revival signal detected'],
            'next_actions'   => ['Send a welcome-back message'],
            'prompt_version' => 'v1.0-report',
        ]);

        $triage = new AiTriageResult([
            'thread_state'        => 'reopened',
            'relationship_health' => 'neutral',
            'actionability'       => 'act_now',
            'success_probability' => 35,
        ]);

        $skill    = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true], $triage);

        $this->assertStringStartsWith('[REOPENED — PROCEED WITH CAUTION]', $response['result']['summary']);
    }

    public function test_it_strips_commercial_next_actions_for_do_not_pursue(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateReport')->andReturn([
            'summary'      => 'Customer is hostile.',
            'key_insights' => ['Do not contact'],
            'next_actions' => [
                'Schedule a meeting with the prospect',
                'Send a product demo link',
                'Follow up on the quote with the prospect',
                'Document the loss internally',
            ],
            'prompt_version' => 'v1.0-report',
        ]);

        $triage = new AiTriageResult([
            'thread_state'        => 'closed_lost',
            'relationship_health' => 'damaged',
            'actionability'       => 'do_not_pursue',
            'success_probability' => 0,
        ]);

        $skill    = new ReportSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockJob, ['enabled' => true], $triage);

        $nextActions = $response['result']['next_actions'];

        $hasCommercial = collect($nextActions)->contains(
            fn($a) => preg_match('/meeting|demo|quote/i', $a) && preg_match('/prospect/i', $a)
        );
        $this->assertFalse($hasCommercial, 'Commercial prospect-facing actions should be stripped.');

        $hasInternal = collect($nextActions)->contains(
            fn($a) => str_contains(strtolower($a), 'internally') || str_contains(strtolower($a), 'document')
        );
        $this->assertTrue($hasInternal, 'Internal next actions should be preserved.');
    }
}
