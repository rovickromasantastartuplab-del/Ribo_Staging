<?php

namespace Tests\Feature\AI;

use App\Models\EmailThread;
use App\Services\AI\Prompts\DraftPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use App\Services\AI\Skills\DraftSkill;
use Mockery;
use Tests\TestCase;

class DraftValidatorTest extends TestCase
{
    private $mockPromptFactory;
    private $mockThread;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPromptFactory = Mockery::mock(DraftPromptFactory::class);
        $this->mockPromptFactory->shouldReceive('buildSystemPrompt')->andReturn('Draft System Prompt');
        $this->mockPromptFactory->shouldReceive('buildUserPrompt')->andReturn('Draft User Prompt');
        
        $this->mockThread = Mockery::mock(EmailThread::class);
        $this->mockThread->shouldReceive('getAttribute')->with('id')->andReturn(123);
        $this->mockThread->shouldReceive('getAttribute')->with('subject')->andReturn('Test Subject');
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_enforces_required_keys_and_falls_back(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateDraft')->andReturn([
            'subject' => 'Missing body test',
            // 'body' is missing
        ]);

        $skill = new DraftSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockThread, 'Keep it short', 'professional', ['enabled' => true]);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('missing_required_key_body', $response['metadata']['fallback_reason']);
        $this->assertEquals('Quick follow-up', $response['result']['subject']);
        $this->assertStringContainsString('Would you like us to schedule a quick next step?', $response['result']['body']);
    }

    public function test_it_trims_long_subjects(): void
    {
        $longSubject = str_repeat('A', 150);
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateDraft')->andReturn([
            'subject' => $longSubject,
            'body' => '<p>Valid body.</p>',
        ]);

        $skill = new DraftSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockThread, 'test', 'test', []);

        $this->assertTrue($response['metadata']['repair_applied']);
        $this->assertEquals('subject_trim', $response['metadata']['repair_type']);
        $this->assertEquals(140, strlen($response['result']['subject']));
    }

    public function test_it_enforces_p_tag_body_format(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateDraft')->andReturn([
            'subject' => 'Valid Subject',
            'body' => 'No paragraph tag here.',
        ]);

        $skill = new DraftSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockThread, 'test', 'test', []);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('invalid_body_format', $response['metadata']['fallback_reason']);
    }

    public function test_it_enforces_single_cta_policy(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateDraft')->andReturn([
            'subject' => 'Valid Subject',
            'body' => '<p>How are you? When can we meet?</p>', // Two questions
        ]);

        $skill = new DraftSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockThread, 'test', 'test', []);

        $this->assertTrue($response['metadata']['fallback_applied']);
        $this->assertEquals('multiple_cta_questions', $response['metadata']['fallback_reason']);
    }

    public function test_it_passes_valid_drafts(): void
    {
        $mockClient = Mockery::mock(OpenAiConversationClient::class);
        $mockClient->shouldReceive('generateDraft')->andReturn([
            'subject' => 'Project Update',
            'body' => '<p>The project is on track. Do you have any questions?</p>',
        ]);

        $skill = new DraftSkill($this->mockPromptFactory, $mockClient);
        $response = $skill->generate($this->mockThread, 'test', 'test', []);

        $this->assertFalse($response['metadata']['fallback_applied']);
        $this->assertEquals('Project Update', $response['result']['subject']);
    }
}
