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
        $this->mockThread->shouldReceive('getAttribute')->with('snippet')->andReturn('Test Snippet');
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

    public function test_it_does_not_treat_outbound_recovery_attempts_as_reopened_threads(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'               => 'We sent an apology and invited them back.',
            'intent'                => 'follow_up',
            'intent_confidence'     => 82,
            'priority'              => 'medium',
            'thread_state'          => 'reopened',
            'relationship_health'   => 'neutral',
            'actionability'         => 'act_now',
            'success_probability'   => 48,
            'behavioral_pulse'      => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Restart the deal',
                'reason'         => 'Apology sent',
                'recommendation' => 'Meetings: Book a recovery call immediately',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $this->mockThread->shouldReceive('getAttribute')->with('gmailAccount')->andReturn((object) ['gmail_address' => 'sales@ribo.test']);
        $this->mockThread->shouldReceive('getAttribute')->with('latestMessage')->andReturn((object) ['from_email' => 'sales@ribo.test']);

        $previousTriage = new AiTriageResult([
            'thread_state' => 'closed_lost',
            'success_probability' => 0,
        ]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result   = $response['result'];

        $this->assertEquals('closed_lost', $result['thread_state']);
        $this->assertLessThanOrEqual(5, $result['success_probability']);
        $this->assertStringContainsString('Wait for explicit inbound', $result['strategic_action_json']['recommendation']);
        $this->assertStringContainsString('outbound_recovery_guard', $response['metadata']['repair_type']);
    }

    public function test_it_clamps_misaligned_threads_and_forces_repair_first_action(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'               => 'The buyer says our process and scope still do not fit.',
            'intent'                => 'sales',
            'intent_confidence'     => 87,
            'priority'              => 'high',
            'thread_state'          => 'misaligned',
            'relationship_health'   => 'positive',
            'actionability'         => 'act_now',
            'success_probability'   => 82,
            'behavioral_pulse'      => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Keep momentum going',
                'reason'         => 'Buyer replied',
                'recommendation' => 'Meetings: Schedule a call to push the deal forward',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);
        $result   = $response['result'];

        $this->assertEquals('misaligned', $result['thread_state']);
        $this->assertEquals('strained', $result['relationship_health']);
        $this->assertLessThanOrEqual(30, $result['success_probability']);
        $this->assertEquals('cooling_down', $result['behavioral_pulse']);
        $this->assertStringStartsWith('Tasks:', $result['strategic_action_json']['recommendation']);
        $this->assertStringContainsString('Clarify the scope, value gap, or process mismatch', $result['strategic_action_json']['recommendation']);
        $this->assertStringContainsString('misalignment_guard', $response['metadata']['repair_type']);
    }

    public function test_it_clamps_objection_threads_and_requires_concern_handling_before_next_step(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'               => 'The prospect repeated the pricing objection.',
            'intent'                => 'sales',
            'intent_confidence'     => 90,
            'priority'              => 'high',
            'thread_state'          => 'objection',
            'relationship_health'   => 'neutral',
            'actionability'         => 'act_now',
            'success_probability'   => 88,
            'behavioral_pulse'      => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Get them on a demo',
                'reason'         => 'Still engaged',
                'recommendation' => 'Meetings: Schedule a pricing call right away',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true]);
        $result   = $response['result'];

        $this->assertEquals('objection', $result['thread_state']);
        $this->assertLessThanOrEqual(55, $result['success_probability']);
        $this->assertEquals('cooling_down', $result['behavioral_pulse']);
        $this->assertStringStartsWith('Tasks:', $result['strategic_action_json']['recommendation']);
        $this->assertStringContainsString('Address the objection directly', $result['strategic_action_json']['recommendation']);
        $this->assertStringContainsString('objection_guard', $response['metadata']['repair_type']);
    }

    public function test_it_blocks_closed_lost_to_active_transition_when_sender_is_outbound(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        // Model skips 'reopened' and jumps directly to 'active' — gap in previous guard
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'We sent an apology. Trying to restart.',
            'intent'               => 'follow_up',
            'intent_confidence'    => 80,
            'priority'             => 'medium',
            'thread_state'         => 'active',   // Model jumped to active, bypassing reopened
            'relationship_health'  => 'neutral',
            'actionability'        => 'act_now',
            'success_probability'  => 55,
            'behavioral_pulse'     => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Win back customer',
                'reason'         => 'Apology sent',
                'recommendation' => 'Meetings: Book recovery call',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        // Latest sender is our own team (outbound)
        $this->mockThread->shouldReceive('getAttribute')->with('gmailAccount')->andReturn((object) ['gmail_address' => 'sales@ribo.test']);
        $this->mockThread->shouldReceive('getAttribute')->with('latestMessage')->andReturn((object) ['from_email' => 'sales@ribo.test']);

        $previousTriage = new AiTriageResult([
            'thread_state'        => 'closed_lost',
            'success_probability' => 0,
        ]);

        $skill    = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result   = $response['result'];

        // Must stay closed_lost — outbound apology cannot revive the thread
        $this->assertEquals('closed_lost', $result['thread_state']);
        $this->assertLessThanOrEqual(5, $result['success_probability']);
        $this->assertStringContainsString('outbound_recovery_guard', $response['metadata']['repair_type']);
    }
}
