<?php

namespace App\Services\AI\Skills;

use App\Models\AiReportJob;
use App\Services\AI\Prompts\ReportPromptFactory;

class ReportSkill
{
    public function __construct(
        private readonly ReportPromptFactory $promptFactory
    ) {
    }

    public function generate(AiReportJob $job): array
    {
        $this->promptFactory->buildSystemPrompt();
        $this->promptFactory->buildUserPrompt($job);

        return [
            'summary' => 'Conversation report generated successfully.',
            'key_insights' => [
                'Stakeholder engagement remains active.',
                'Follow-up action is recommended within 24 hours.',
            ],
            'next_actions' => [
                'Send progress update',
                'Confirm timeline and ownership',
            ],
            'prompt_version' => 'report-v1',
        ];
    }
}
