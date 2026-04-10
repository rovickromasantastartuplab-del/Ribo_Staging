<?php

namespace App\Services\AI\Skills;

use App\Models\AiReportJob;
use App\Models\AiTriageResult;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class ReportSkill
{
    public function __construct(
        private readonly ReportPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function generate(AiReportJob $job, array $config, ?AiTriageResult $triage = null): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($job, $triage);

        $raw = $this->provider->generateReport($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'scope' => $job->getAttribute('scope') ?: 'thread',
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

        if (!$metadata['fallback_applied'] && $triage !== null) {
            $validated = $this->enforceTriageFraming($validated, $triage, $metadata);
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
        $this->markRepair($metadata, 'fallback_report');

        return $data;
    }

    private function enforceTriageFraming(array $data, AiTriageResult $triage, array &$metadata): array
    {
        $threadState = $triage->thread_state ?? '';
        $actionable = $triage->actionability ?? '';
        $probability = (int) ($triage->success_probability ?? 100);
        $insights = collect($data['key_insights'] ?? [])
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values();
        $actions = collect($data['next_actions'] ?? [])
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values();

        if ($threadState === 'closed_lost' && !str_starts_with($data['summary'] ?? '', '[CLOSED LOST]')) {
            $data['summary'] = '[CLOSED LOST] ' . ($data['summary'] ?? '');
            $this->markRepair($metadata, 'closed_lost_prefix');
        }

        if ($threadState === 'reopened' && !str_starts_with($data['summary'] ?? '', '[REOPENED')) {
            $data['summary'] = '[REOPENED - PROCEED WITH CAUTION] ' . ($data['summary'] ?? '');
            $this->markRepair($metadata, 'reopened_prefix');
        }

        if ($threadState === 'misaligned' && !str_starts_with($data['summary'] ?? '', '[MISALIGNED]')) {
            $data['summary'] = '[MISALIGNED] ' . ($data['summary'] ?? '');
            $this->markRepair($metadata, 'misaligned_prefix');
        }

        if ($threadState === 'misaligned' && !$this->containsAny($insights->all(), ['mismatch', 'misalign', 'fit', 'scope', 'value', 'process', 'expectation'])) {
            $mismatchType = $this->inferMisalignmentType($triage);
            $insights->prepend("Triage indicates a {$mismatchType} mismatch that is blocking forward motion.");
            $this->markRepair($metadata, 'misaligned_insight');
        }

        if ($threadState === 'reopened' && !$this->containsAny($insights->all(), ['revival', 'reopened', 'restart', 're-engagement'])) {
            $insights->prepend('The thread has reopened on an inbound revival signal and still requires cautious follow-through.');
            $this->markRepair($metadata, 'reopened_insight');
        }

        if ($probability <= 20 && !$this->containsAny($insights->all(), ['probability', 'risk', 'low likelihood', 'fragile'])) {
            $insights->push("Success probability is currently constrained at {$probability}%, so leadership should treat the thread as elevated risk.");
            $this->markRepair($metadata, 'probability_context');
        }

        if (in_array($actionable, ['do_not_pursue', 'archive'], true) || $probability <= 5) {
            $actions = $actions->filter(fn (string $action): bool => !$this->isCommercialProspectAction($action))->values();
            if ($actions->isEmpty()) {
                $actions = collect(['Document the state internally and avoid further commercial outreach.']);
            }
            $this->markRepair($metadata, 'terminal_action_gating');
        }

        if ($threadState === 'misaligned') {
            $mismatchType = $this->inferMisalignmentType($triage);
            $actions = $actions->filter(fn (string $action): bool => !$this->isMisalignedCommercialPush($action))->values();
            if ($actions->isEmpty()) {
                $actions = collect(["Clarify the {$mismatchType} mismatch before proposing another customer-facing step."]);
            }
            $this->markRepair($metadata, 'misaligned_action_gating');
        }

        if ($actionable === 'monitor') {
            $actions = $actions->filter(fn (string $action): bool => !$this->isAggressivePushAction($action))->values();
            if ($actions->isEmpty()) {
                $actions = collect(['Monitor for an explicit inbound customer signal before taking another action.']);
            }
            $this->markRepair($metadata, 'monitor_action_gating');
        }

        $data['key_insights'] = $insights->values()->all();
        $data['next_actions'] = $actions->values()->all();

        return $data;
    }

    private function containsAny(array $items, array $needles): bool
    {
        $haystack = strtolower(implode(' ', $items));

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function inferMisalignmentType(AiTriageResult $triage): string
    {
        $context = strtolower(implode(' ', [
            (string) ($triage->summary ?? ''),
            (string) ($triage->strategic_action_json['reason'] ?? ''),
            (string) ($triage->strategic_action_json['recommendation'] ?? ''),
        ]));

        if (str_contains($context, 'scope')) {
            return 'scope';
        }

        if (str_contains($context, 'value') || str_contains($context, 'roi') || str_contains($context, 'price')) {
            return 'value';
        }

        if (str_contains($context, 'expect')) {
            return 'expectation';
        }

        if (str_contains($context, 'process') || str_contains($context, 'approval') || str_contains($context, 'buy')) {
            return 'process';
        }

        return 'fit';
    }

    private function isCommercialProspectAction(string $action): bool
    {
        // Match any outward-facing commercial action, regardless of whether "prospect/customer" is mentioned.
        // Preserve internal-only actions (document, notify team, update CRM, etc.).
        if (!preg_match('/meeting|demo|quote|proposal|call|schedule|send.*link|book/i', $action)) {
            return false;
        }

        // Explicitly preserve internal operational actions that happen to mention these words
        if (preg_match('/\b(internal|internally|team|CRM|record|document|log|update|note)\b/i', $action)) {
            return false;
        }

        return true;
    }

    private function isMisalignedCommercialPush(string $action): bool
    {
        return (bool) preg_match('/schedule|meeting|demo|quote|proposal|call/i', $action);
    }

    private function isAggressivePushAction(string $action): bool
    {
        return (bool) preg_match('/schedule|meeting|demo|quote|proposal|push|follow up/i', $action);
    }

    private function markRepair(array &$metadata, string $repairType): void
    {
        $metadata['repair_applied'] = true;
        $metadata['repair_type'] = $metadata['repair_type']
            ? $metadata['repair_type'] . ',' . $repairType
            : $repairType;
    }
}
