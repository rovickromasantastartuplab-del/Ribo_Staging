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

        $raw = $this->provider->generateReport($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'job_id' => $job->id,
            'scope' => $job->scope,
            'prompt_version' => ReportPromptFactory::VERSION,
        ]);

        $metadata = [
            'prompt_version' => ReportPromptFactory::VERSION,
            'validation_stage_failed' => null,
            'repair_applied' => false,
            'repair_type' => null,
            'fallback_applied' => false,
            'fallback_reason' => null,
        ];

        $validated = $this->validateParse($raw, $metadata);
        if (!$metadata['fallback_applied']) {
            $validated = $this->validatePolicy($validated, $metadata);
        }
        if ($metadata['fallback_applied']) {
            $validated = $this->applyRepair($validated, $metadata);
        }

        $validated['prompt_version'] = ReportPromptFactory::VERSION;

        return [
            'result' => $validated,
            'metadata' => $metadata,
        ];
    }

    private function validateParse(array $data, array &$metadata): array
    {
        $requiredKeys = ['summary', 'key_insights', 'next_actions'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                $metadata['validation_stage_failed'] = 'parse';
                $metadata['fallback_applied'] = true;
                $metadata['fallback_reason'] = "missing_required_key_{$key}";
                return $data;
            }
        }

        return $data;
    }

    private function validatePolicy(array $data, array &$metadata): array
    {
        $summary = trim((string) ($data['summary'] ?? ''));
        $insights = $data['key_insights'] ?? [];
        $actions = $data['next_actions'] ?? [];

        if ($summary === '' || !is_array($insights) || !is_array($actions)) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'invalid_report_contract';
            return $data;
        }

        $insights = collect($insights)
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        $actions = collect($actions)
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        if (count($insights) === 0 || count($actions) === 0) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'empty_insights_or_actions';
            return $data;
        }

        $data['key_insights'] = $insights;
        $data['next_actions'] = $actions;
        return $data;
    }

    private function applyRepair(array $data, array &$metadata): array
    {
        $data['summary'] = 'Manual executive review is recommended due to low confidence report quality.';
        $data['key_insights'] = ['Signal quality was insufficient for a reliable automated report.'];
        $data['next_actions'] = ['Assign an owner to manually review recent conversation context.'];
        $metadata['repair_applied'] = true;
        $metadata['repair_type'] = $metadata['repair_type']
            ? $metadata['repair_type'] . ',fallback_report'
            : 'fallback_report';

        return $data;
    }
}
