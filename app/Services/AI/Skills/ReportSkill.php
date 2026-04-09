<?php

namespace App\Services\AI\Skills;

use App\Models\AiReportJob;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class ReportSkill
{
    public function __construct(
        private readonly ReportPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function generate(AiReportJob $job, array $config): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($job);

        return $this->provider->generateReport($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'job_id' => $job->id,
            'scope' => $job->scope,
        ]);
    }
}
