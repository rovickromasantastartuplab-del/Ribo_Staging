<?php

namespace Tests\Feature\AI;

use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Services\AI\Prompts\MemoryPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\MemorySkill;
use Mockery;
use Tests\TestCase;

class MemoryValidatorTest extends TestCase
{
    private $mockPromptFactory;
    private $mockContact;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPromptFactory = Mockery::mock(MemoryPromptFactory::class);
        $this->mockPromptFactory->shouldReceive('buildSystemPrompt')->andReturn('Memory System Prompt');
        $this->mockPromptFactory->shouldReceive('buildUserPrompt')->andReturn('Memory User Prompt');
        
        $this->mockContact = Mockery::mock(Contact::class);
        $this->mockContact->shouldReceive('getAttribute')->with('id')->andReturn(456);
        $this->mockContact->shouldReceive('getAttribute')->with('name')->andReturn('John Doe');
        $this->mockContact->shouldReceive('getAttribute')->with('email')->andReturn('john@example.com');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_enforces_required_keys_and_falls_back(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary' => 'Missing strength test',
            // 'relationship_strength' is missing
        ]);

        $skill = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('missing_required_key_relationship_strength', $response['metadata']['fallback_reason']);
        $this->assertEquals('Relationship memory needs manual review from recent conversations.', $response['result']['relationship_summary']);
    }

    public function test_it_enforces_relationship_strength_enum(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary' => 'Strength enum test',
            'relationship_strength' => 'super_strong', // Invalid enum
            'memory_points_json' => ['Some point'],
        ]);

        $skill = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('invalid_memory_contract', $response['metadata']['fallback_reason']);
    }

    public function test_it_enforces_non_empty_memory_points(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary' => 'Empty points test',
            'relationship_strength' => 'strong',
            'memory_points_json' => [], // Empty array
        ]);

        $skill = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('empty_memory_points', $response['metadata']['fallback_reason']);
    }

    public function test_it_passes_valid_memory(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary' => 'Prospective client with high interest.',
            'relationship_strength' => 'strong',
            'memory_points_json' => ['Expressed interest in Q4 demo', 'Budget approved'],
        ]);

        $skill = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true]);

        $this->assertFalse($response['metadata']['fallback_applied']);
        $this->assertEquals('strong', $response['result']['relationship_strength']);
        $this->assertCount(2, $response['result']['memory_points_json']);
    }

    public function test_it_clamps_relationship_strength_to_weak_when_latest_thread_is_closed_lost(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'Customer was previously active.',
            'relationship_strength' => 'strong', // AI incorrectly says strong
            'memory_points_json'    => ['Had great calls in the past'],
        ]);

        $triageContext = [
            ['thread_state' => 'closed_lost', 'relationship_health' => 'neutral', 'behavioral_pulse' => 'broken', 'is_latest' => true],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $this->assertEquals('weak', $response['result']['relationship_strength']);
    }

    public function test_it_clamps_relationship_strength_to_weak_when_latest_health_is_damaged(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'Customer was hostile.',
            'relationship_strength' => 'moderate', // Should be clamped to weak
            'memory_points_json'    => ['Customer threatened legal action'],
        ]);

        $triageContext = [
            ['thread_state' => 'active', 'relationship_health' => 'damaged', 'behavioral_pulse' => 'cooling_down', 'is_latest' => true],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $this->assertEquals('weak', $response['result']['relationship_strength']);
    }

    public function test_it_clamps_relationship_strength_to_moderate_when_latest_thread_is_reopened(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'Customer came back after stepping away.',
            'relationship_strength' => 'strong', // AI over-optimistic, should be capped at moderate
            'memory_points_json'    => ['Returned after 3 months away'],
        ]);

        $triageContext = [
            ['thread_state' => 'reopened', 'relationship_health' => 'neutral', 'behavioral_pulse' => 'heating_up', 'is_latest' => true],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $this->assertNotEquals('strong', $response['result']['relationship_strength']);
        $this->assertContains($response['result']['relationship_strength'], ['weak', 'moderate']);
    }

    public function test_it_appends_positive_trend_memory_point_for_consistent_healthy_threads(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'Customer is consistently engaged.',
            'relationship_strength' => 'strong',
            'memory_points_json'    => ['Active in all recent threads'],
        ]);

        $triageContext = [
            ['thread_state' => 'active', 'relationship_health' => 'positive', 'behavioral_pulse' => 'heating_up', 'is_latest' => true],
            ['thread_state' => 'active', 'relationship_health' => 'positive', 'behavioral_pulse' => 'stable', 'is_latest' => false],
            ['thread_state' => 'active', 'relationship_health' => 'positive', 'behavioral_pulse' => 'heating_up', 'is_latest' => false],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $points = $response['result']['memory_points_json'];
        $hasTrendPoint = collect($points)->contains(
            fn($p) => str_contains(strtolower($p), 'consistent') || str_contains(strtolower($p), 'momentum')
        );
        $this->assertTrue($hasTrendPoint, 'Expected a positive trend memory point to be injected.');
    }

    public function test_it_records_repeated_friction_from_triage_history_without_rejudging_the_contact(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'Relationship has some mixed history.',
            'relationship_strength' => 'moderate',
            'memory_points_json'    => ['There have been several back-and-forth conversations'],
        ]);

        $triageContext = [
            ['thread_state' => 'objection', 'relationship_health' => 'strained', 'behavioral_pulse' => 'cooling_down', 'is_latest' => true],
            ['thread_state' => 'objection', 'relationship_health' => 'strained', 'behavioral_pulse' => 'stable', 'is_latest' => false],
            ['thread_state' => 'misaligned', 'relationship_health' => 'strained', 'behavioral_pulse' => 'cooling_down', 'is_latest' => false],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $this->assertTrue(
            collect($response['result']['memory_points_json'])->contains(
                fn (string $point): bool => str_contains(strtolower($point), 'repeated')
                    && (str_contains(strtolower($point), 'friction') || str_contains(strtolower($point), 'objection'))
            ),
            'Expected triage-history friction to be preserved as a memory point.'
        );
    }

    public function test_it_records_cautious_revival_when_history_moves_from_closed_lost_to_reopened(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('summarizeMemory')->andReturn([
            'relationship_summary'  => 'The relationship looks fully back on track.',
            'relationship_strength' => 'strong',
            'memory_points_json'    => ['Customer restarted the conversation'],
        ]);

        $triageContext = [
            ['thread_state' => 'reopened', 'relationship_health' => 'neutral', 'behavioral_pulse' => 'heating_up', 'is_latest' => true],
            ['thread_state' => 'closed_lost', 'relationship_health' => 'strained', 'behavioral_pulse' => 'broken', 'is_latest' => false],
        ];

        $skill    = new MemorySkill($this->mockPromptFactory, $mockClient);
        $response = $skill->summarize($this->mockContact, ['enabled' => true], $triageContext);

        $this->assertEquals('moderate', $response['result']['relationship_strength']);
        $this->assertStringContainsString('reopened', strtolower($response['result']['relationship_summary']));
        $this->assertTrue(
            collect($response['result']['memory_points_json'])->contains(
                fn (string $point): bool => str_contains(strtolower($point), 'reopened')
                    || str_contains(strtolower($point), 'revived')
            ),
            'Expected the closed_lost -> reopened transition to be preserved in memory.'
        );
    }
}
