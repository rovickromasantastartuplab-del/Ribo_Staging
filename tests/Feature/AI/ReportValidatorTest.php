<?php

namespace Tests\Feature\AI;

use App\Models\AiReportJob;
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
}
