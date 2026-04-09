<?php

namespace Tests\Feature\AI;

use App\Models\EmailThread;
use App\Services\AI\Prompts\TriagePromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\TriageSkill;
use Mockery;
use Tests\TestCase;

class AiTriageRealityTest extends TestCase
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

    public function test_it_clamps_probability_and_overrides_recommendation_for_closed_lost_deals(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        
        // Mock an "optimistic" but "closed_lost" response from the LLM
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary' => 'Customer decided to step back.',
            'intent' => 'follow_up',
            'intent_confidence' => 100,
            'priority' => 'high',
            'thread_state' => 'closed_lost',
            'relationship_health' => 'damaged',
            'actionability' => 'archive',
            'success_probability' => 80, // Optimistic probability that should be clamped
            'behavioral_pulse' => 'stable',
            'strategic_action_json' => [
                'goal' => 'Follow up anyway',
                'reason' => 'Always be closing',
                'recommendation' => 'Meetings: Schedule a call to win them back' // Aggressive recommendation
            ],
            'prompt_version' => 'v1.1-reality'
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $result = $response['result'];
        $metadata = $response['metadata'];

        // Assert probability is clamped
        $this->assertLessThanOrEqual(5, $result['success_probability']);
        $this->assertEquals('broken', $result['behavioral_pulse']);
        
        // Assert recommendation is overridden
        $this->assertStringContainsString('Archive thread', $result['strategic_action_json']['recommendation']);
        $this->assertEquals('Cease interaction', $result['strategic_action_json']['goal']);

        // Assert metadata flags the override
        $this->assertTrue($metadata['repair_applied']);
        $this->assertStringContainsString('terminal_override', $metadata['repair_type']);
    }

    public function test_it_handles_damaged_relationships_by_suppressing_aggressive_actions(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);

        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary' => 'Customer is very angry.',
            'intent' => 'general',
            'intent_confidence' => 90,
            'priority' => 'medium',
            'thread_state' => 'active',
            'relationship_health' => 'damaged',
            'actionability' => 'monitor',
            'success_probability' => 10,
            'behavioral_pulse' => 'cooling_down',
            'strategic_action_json' => [
                'goal' => 'Resolve anger',
                'reason' => 'Customer is ranting',
                'recommendation' => 'Meetings: Call them to apologize' // Aggressive recommendation
            ],
            'prompt_version' => 'v1.1-reality'
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);

        $result = $response['result'];
        
        // Assert recommendation is suppressed
        $this->assertStringContainsString('Review hostile sentiment', $result['strategic_action_json']['recommendation']);
        $this->assertTrue($response['metadata']['repair_applied']);
        $this->assertStringContainsString('action_suppression', $response['metadata']['repair_type']);
    }
}
