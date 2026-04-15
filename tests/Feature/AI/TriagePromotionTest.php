<?php

namespace Tests\Feature\AI;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Prompts\TriagePromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\TriageSkill;
use Mockery;
use Tests\TestCase;

class TriagePromotionTest extends TestCase
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
        // Mock snippet to include "send over the proposal" to trigger promotion
        $this->mockThread->shouldReceive('getAttribute')->with('snippet')->andReturn('Thank you. Please send over the proposal as discussed.');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_promotes_reopened_to_active_on_concrete_business_motion(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        
        // AI stays cautious and returns 'reopened'
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'Customer accepted apology. Tentative re-engagement.',
            'intent'               => 'sales',
            'intent_confidence'    => 85,
            'priority'             => 'medium',
            'thread_state'         => 'reopened',
            'relationship_health'  => 'neutral',
            'actionability'        => 'act_now',
            'success_probability'  => 30,
            'behavioral_pulse'     => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Manage revival',
                'reason'         => 'Revival detected',
                'recommendation' => 'Tasks: Follow up',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $previousTriage = new AiTriageResult([
            'thread_state' => 'reopened', 
            'success_probability' => 30
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result = $response['result'];

        // Assert promotion to active
        $this->assertEquals('active', $result['thread_state']);
        // Assert probability is raised to at least 35%
        $this->assertGreaterThanOrEqual(35, $result['success_probability']);
        // Assert metadata flags the promotion
        $this->assertTrue($response['metadata']['repair_applied']);
        $this->assertStringContainsString('active_promotion', $response['metadata']['repair_type']);
    }

    public function test_it_tones_down_hot_pulse_for_low_probability_reopened_threads(): void
    {
         $mockClient = Mockery::mock(OpenAiConversationClient::class);
        
        // AI returns HOT pulse for a 25% prob thread
        $mockClient->shouldReceive('analyzeTriage')->andReturn([
            'summary'              => 'Customer said maybe later.',
            'intent'               => 'follow_up',
            'intent_confidence'    => 70,
            'priority'             => 'low',
            'thread_state'         => 'reopened',
            'relationship_health'  => 'neutral',
            'actionability'        => 'monitor',
            'success_probability'  => 25,
            'behavioral_pulse'     => 'heating_up',
            'strategic_action_json' => [
                'goal'           => 'Watch reviving lead',
                'reason'         => 'Weak revival',
                'recommendation' => 'Tasks: Set reminder',
            ],
            'prompt_version' => 'v1.1-expert',
        ]);

        $previousTriage = new AiTriageResult([
            'thread_state' => 'closed_lost', 
            'success_probability' => 0
        ]);

        $skill = new TriageSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->analyze($this->mockThread, ['enabled' => true], $previousTriage);
        $result = $response['result'];

        // Assert pulse is toned down to stable if prob is low
        $this->assertEquals('stable', $result['behavioral_pulse']);
    }
}
