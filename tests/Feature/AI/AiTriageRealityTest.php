<?php

namespace Tests\Feature\AI;

use App\Models\AiTriageResult;
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

    public function test_it_clamps_reopened_probability_between_25_and_45_when_previous_was_closed_lost(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'Customer wants to restart.',
            'intent'               => 'sales',
            'intent_confidence'    => 90,
            'priority'             => 'high',
            'thread_state'         => 'reopened',
            'relationship_health'  => 'neutral',
            'actionability'        => 'monitor', // AI was passive — should be overridden to act_now
            'success_probability'  => 70,        // Too high — should be clamped to 25–45
            'behavioral_pulse'     => 'stable',  // Should be overridden to heating_up
            'strategic_action_json' => [
                'goal'           => 'Re-engage',
                'reason'         => 'Revival signal',
                'recommendation' => 'Meetings: Schedule welcome-back call',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $previousTriage = new AiTriageResult(['thread_state' => 'closed_lost', 'success_probability' => 0]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result   = $response['result'];

        $this->assertEquals('reopened', $result['thread_state']);
        $this->assertGreaterThanOrEqual(25, $result['success_probability']);
        $this->assertLessThanOrEqual(45, $result['success_probability']);
        $this->assertEquals('act_now', $result['actionability']);
        $this->assertEquals('heating_up', $result['behavioral_pulse']);
    }

    public function test_it_prevents_probability_increase_when_objection_is_unresolved(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'Customer raised the same pricing concern again.',
            'intent'               => 'sales',
            'intent_confidence'    => 85,
            'priority'             => 'medium',
            'thread_state'         => 'objection',
            'relationship_health'  => 'strained',
            'actionability'        => 'act_now',
            'success_probability'  => 75, // Higher than previous — should not be allowed
            'behavioral_pulse'     => 'cooling_down',
            'strategic_action_json' => [
                'goal'           => 'Address objection',
                'reason'         => 'Price concern repeated',
                'recommendation' => 'Tasks: Follow up on pricing concern',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $previousTriage = new AiTriageResult(['thread_state' => 'objection', 'success_probability' => 55]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result   = $response['result'];

        $this->assertEquals('objection', $result['thread_state']);
        $this->assertLessThanOrEqual(55, $result['success_probability']);
    }

    public function test_it_advances_reopened_to_active_on_confirming_message(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'Customer sent a normal business follow-up.',
            'intent'               => 'sales',
            'intent_confidence'    => 88,
            'priority'             => 'medium',
            'thread_state'         => 'active',   // AI correctly sees normal progression
            'relationship_health'  => 'neutral',
            'actionability'        => 'act_now',
            'success_probability'  => 50,
            'behavioral_pulse'     => 'stable',
            'strategic_action_json' => [
                'goal'           => 'Continue engagement',
                'reason'         => 'Normal follow-up after revival',
                'recommendation' => 'Leads: Move to qualified stage',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $previousTriage = new AiTriageResult(['thread_state' => 'reopened', 'success_probability' => 35]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result   = $response['result'];

        // Confirming message after reopened: stays active (no forced regression)
        $this->assertEquals('active', $result['thread_state']);
        $this->assertGreaterThanOrEqual(35, $result['success_probability']);
    }
}
