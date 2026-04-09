<?php

namespace Tests\Feature\AI;

use App\Models\AiUsageLog;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\AI\Prompts\TriagePromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\TriageSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TriageValidatorTest extends TestCase
{
    private $mockPromptFactory;
    private $mockThread;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPromptFactory = Mockery::mock(TriagePromptFactory::class);
        $this->mockPromptFactory->shouldReceive('buildSystemPrompt')->andReturn('Expert System Prompt');
        $this->mockPromptFactory->shouldReceive('buildUserPrompt')->andReturn('Expert User Prompt');
        
        $this->mockThread = Mockery::mock(EmailThread::class);
        $this->mockThread->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $this->mockThread->shouldReceive('getAttribute')->with('subject')->andReturn('Test Subject');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_enforces_module_prefix_and_repairs_via_fallback(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'intent' => 'sales',
            'intent_confidence' => 90,
            'priority' => 'high',
            'success_probability' => 80,
            'behavioral_pulse' => 'stable',
            'summary' => 'Bad recommendation test.',
            'strategic_action_json' => [
                'goal' => 'Test',
                'reason' => 'Test',
                'recommendation' => 'Something: Without a valid prefix',
            ],
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $this->assertEquals('Leads: Review sales opportunity manually.', $response['result']['strategic_action_json']['recommendation']);
        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('invalid_module_prefix', $response['metadata']['fallback_reason']);
        $this->assertEquals(60, $response['result']['intent_confidence']);
        $this->assertEquals(50, $response['result']['success_probability']);
    }

    public function test_it_checks_semantic_keyword_alignment(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'intent' => 'billing',
            'intent_confidence' => 100,
            'priority' => 'medium',
            'success_probability' => 100,
            'behavioral_pulse' => 'stable',
            'summary' => 'Semantic mismatch test.',
            'strategic_action_json' => [
                'goal' => 'Test',
                'reason' => 'Test',
                'recommendation' => 'Invoices: Pitch a new product demo.', // "demo" is for Meetings, not Invoices
            ],
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $this->assertEquals('Invoices: Review billing thread manually.', $response['result']['strategic_action_json']['recommendation']);
        $this->assertEquals('semantic_module_mismatch', $response['metadata']['fallback_reason']);
    }

    public function test_it_gates_urgent_priority_for_non_sales_billing_intents(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'intent' => 'support',
            'intent_confidence' => 90,
            'priority' => 'urgent',
            'success_probability' => 90,
            'behavioral_pulse' => 'stable',
            'summary' => 'Urgency inflation test.',
            'strategic_action_json' => [
                'goal' => 'Test',
                'reason' => 'Test',
                'recommendation' => 'Tasks: Fix a minor bug.',
            ],
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $this->assertEquals('high', $response['result']['priority']);
        $this->assertTrue($response['metadata']['repair_applied']);
        $this->assertEquals('urgency_downgrade', $response['metadata']['repair_type']);
    }

    public function test_it_passes_valid_expert_outputs(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'intent' => 'sales',
            'intent_confidence' => 95,
            'priority' => 'urgent',
            'success_probability' => 85,
            'behavioral_pulse' => 'heating_up',
            'summary' => 'Valid expert output.',
            'strategic_action_json' => [
                'goal' => 'Book demo',
                'reason' => 'High intent',
                'recommendation' => 'Meetings: Propose a demo walkthrough.',
            ],
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $this->assertFalse($response['metadata']['fallback_applied']);
        $this->assertEquals('Meetings: Propose a demo walkthrough.', $response['result']['strategic_action_json']['recommendation']);
        $this->assertEquals('urgent', $response['result']['priority']);
    }
}

