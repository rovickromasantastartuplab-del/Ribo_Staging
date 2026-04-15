<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'summary' => 'Default test summary.',
                    'intent' => 'general',
                    'intent_confidence' => 60,
                    'priority' => 'medium',
                    'success_probability' => 50,
                    'behavioral_pulse' => 'stable',
                    'strategic_action' => [
                        'goal' => 'continue_conversation',
                        'reason' => 'default_fixture',
                        'recommendation' => 'Follow up with concise next steps.',
                    ],
                    'relationship_summary' => 'Default relationship summary.',
                    'relationship_strength' => 'moderate',
                    'memory_points' => ['default_memory_point'],
                    'subject' => 'Re: Default',
                    'body' => '<p>Default test body</p>',
                    'key_insights' => ['Default insight'],
                    'next_actions' => ['Default next action'],
                ]),
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 12,
                    'total_tokens' => 22,
                ],
            ], 200),
        ]);
    }
}
