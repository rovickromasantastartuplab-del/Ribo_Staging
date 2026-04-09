<?php

namespace App\Services\AI\Prompts;

use App\Models\AiReportJob;

class ReportPromptFactory
{
    public function buildSystemPrompt(): string
    {
        return 'You generate concise conversation reports with status, insights, and next actions.';
    }

    public function buildUserPrompt(AiReportJob $job): string
    {
        $scope = $job->scope ?: 'overall';
        $threadId = $job->email_thread_id ?: 'none';
        $contactId = $job->contact_id ?: 'none';

        return "Scope: {$scope}\nThread ID: {$threadId}\nContact ID: {$contactId}";
    }
}
