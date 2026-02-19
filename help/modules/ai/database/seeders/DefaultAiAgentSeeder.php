<?php

namespace Ai\Database\Seeders;

use Ai\AiAgent\Models\AiAgent;
use Illuminate\Database\Seeder;

class DefaultAiAgentSeeder extends Seeder
{
    public function run(): void
    {
        if (!AiAgent::count()) {
            AiAgent::create([
                'enabled' => true,
                'config' => [
                    'name' => 'AI assistant',
                    'greetingType' => 'basicGreeting',
                    'basicGreeting' => [
                        'message' => 'Hello! How can I help you today?',
                        'flowIds' => [],
                    ],
                ],
            ]);
        }
    }
}
