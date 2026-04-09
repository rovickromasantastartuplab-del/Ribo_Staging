<?php

namespace Tests\Feature\AI;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\AI\ConversationAiTriageService;
use App\Services\AI\Skills\TriageSkill;
use App\Services\AI\ConversationAiConfigService;
use App\Services\AI\ConversationAiTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Carbon\Carbon;

class AiStalenessRefreshTest extends TestCase
{
    use RefreshDatabase;

    private $mockTriageSkill;
    private $mockConfigService;
    private $mockTelemetryService;
    private $user;
    private $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['type' => 'company']);
        $this->companyId = $this->user->id;
        
        $this->mockTriageSkill = Mockery::mock(TriageSkill::class);
        $this->mockConfigService = Mockery::mock(ConversationAiConfigService::class);
        $this->mockTelemetryService = Mockery::mock(ConversationAiTelemetryService::class);
        
        $this->mockConfigService->shouldReceive('resolve')->andReturn(['model' => 'gpt-test']);
    }

    public function test_triage_auto_refreshes_on_new_message(): void
    {
        $thread = EmailThread::factory()->create([
            'created_by' => $this->companyId,
            'last_message_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);

        // First call: Should analyze because no cache exists
        $this->mockTriageSkill->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => [
                    'intent' => 'sales',
                    'intent_confidence' => 90,
                    'priority' => 'high',
                    'success_probability' => 80,
                    'behavioral_pulse' => 'stable',
                    'summary' => 'Initial analysis',
                    'strategic_action_json' => ['goal' => 'test', 'recommendation' => 'Initial'],
                ],
                'metadata' => ['prompt_version' => 'v1']
            ]);

        $this->mockTelemetryService->shouldReceive('recordSuccess')->twice();

        $service = new ConversationAiTriageService(
            $this->mockConfigService,
            $this->mockTriageSkill,
            $this->mockTelemetryService
        );

        $result1 = $service->show($thread, $companyId = $this->companyId);
        $this->assertEquals('Initial analysis', $result1->summary);
        
        // Update analyzed_at to match the "past"
        $result1->update(['analyzed_at' => Carbon::parse('2026-01-01 10:00:05')]);

        // Second call: Should return CACHED because no new messages
        $result2 = $service->show($thread, $this->companyId);
        $this->assertEquals($result1->id, $result2->id);

        // Third call: New message arrives (last_message_at > analyzed_at + 5s)
        $thread->update(['last_message_at' => Carbon::parse('2026-01-01 10:00:15')]);

        $this->mockTriageSkill->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => [
                    'intent' => 'sales',
                    'intent_confidence' => 90,
                    'priority' => 'high',
                    'success_probability' => 85,
                    'behavioral_pulse' => 'stable',
                    'summary' => 'Updated analysis',
                    'strategic_action_json' => ['goal' => 'test', 'recommendation' => 'Updated'],
                ],
                'metadata' => ['prompt_version' => 'v1']
            ]);

        $result3 = $service->show($thread, $this->companyId);
        $this->assertEquals('Updated analysis', $result3->summary);
        $this->assertNotEquals($result1->updated_at, $result3->updated_at);
    }
}
