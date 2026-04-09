<?php

namespace Tests\Feature\AI;

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
}
