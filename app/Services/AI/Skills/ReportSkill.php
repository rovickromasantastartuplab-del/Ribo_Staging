<?php

namespace App\Services\AI\Skills;

use App\Models\AiReportJob;
use App\Models\AiTriageResult;
use App\Services\AI\Prompts\ReportPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;
use Illuminate\Support\Facades\Log;

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

        Log::debug('[ReportSkill] Raw AI response received', [
            'job_id' => $job->id,
            'scope' => $job->scope,
            'keys_returned' => array_keys($raw),
            'has_summary' => isset($raw['summary']),
            'has_key_insights' => isset($raw['key_insights']),
            'has_next_actions' => isset($raw['next_actions']),
            'has_key_risks' => isset($raw['key_risks']),
            'has_usage_signal' => isset($raw['usage_signal']),
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
            Log::warning('[ReportSkill] Fallback triggered', [
                'job_id' => $job->id,
                'stage_failed' => $metadata['validation_stage_failed'],
                'reason' => $metadata['fallback_reason'],
            ]);
            $validated = $this->applyRepair($validated, $metadata);
        } else if ($triage !== null) {
            Log::debug('[ReportSkill] Triage framing applied', [
                'job_id' => $job->id,
                'thread_state' => $triage->thread_state,
                'actionability' => $triage->actionability,
            ]);
            $validated = $this->enforceTriageFraming($validated, $triage, $metadata);
        }

        // Always ensure the structure is complete and normalized regardless of flow path
        $validated = $this->ensureStructuredSections($validated);

        Log::debug('[ReportSkill] Final output ready', [
            'job_id' => $job->id,
            'fallback_applied' => $metadata['fallback_applied'],
            'repair_type' => $metadata['repair_type'],
            'key_risks_count' => count($validated['key_risks'] ?? []),
            'growth_opps_count' => count($validated['growth_opportunities'] ?? []),
            'exec_insights_count' => count($validated['executive_insights'] ?? []),
        ]);

        $validated['prompt_version'] = ReportPromptFactory::VERSION;

        return [
            'result' => $validated,
            'metadata' => $metadata,
        ];
    }

    private function validateParse(array $data, array &$metadata): array
    {
        // Only fail-fast if the absolute core keys are missing.
        // Secondary keys (health signals, risks, etc.) are filled gracefully by ensureStructuredSections().
        $requiredKeys = ['summary', 'key_insights', 'next_actions'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                Log::warning('[ReportSkill] validateParse failed — missing key', ['key' => $key, 'available_keys' => array_keys($data)]);
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
        $data['account_status'] = 'Insufficient AI confidence to classify account status.';
        $data['executive_insights'] = ['Automation fallback triggered due to schema or policy validation failure.'];
        $data['key_relationships'] = [['name' => 'Not available', 'role' => 'Stakeholder', 'strength' => 'Medium']];
        $data['key_risks'] = ['Signal quality was insufficient for granular risk detection.'];
        $data['growth_opportunities'] = ['Signal quality was insufficient for granular opportunity detection.'];
        $data['usage_signal'] = 'Not available';
        $data['support_signal'] = 'Not available';
        $data['sentiment_signal'] = 'Not available';
        $data['engagement_pattern'] = 'Not available';
        $data['role_based_actions'] = [
            'sales' => ['Confirm active commercial motion before outreach.'],
            'csm' => ['Validate customer sentiment from recent interactions.'],
            'support' => ['Review unresolved tickets that may impact relationship health.'],
            'exec_sponsor' => ['Monitor account for senior stakeholder engagement signals.'],
        ];
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

    private function ensureStructuredSections(array $data): array
    {
        $summary = trim((string) ($data['summary'] ?? ''));
        $insights = collect($data['key_insights'] ?? [])
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();
        $actions = collect($data['next_actions'] ?? [])
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        $data['normalized_status'] = $this->normalizeStatus($data);
        $data['normalized_health_score'] = $this->normalizeEnum(
            $data['health_score'] ?? null,
            ['High', 'Medium', 'Low'],
            'Medium'
        );

        $data['account_status'] = trim((string) ($data['account_status'] ?? ''));
        if ($data['account_status'] === '') {
            $data['account_status'] = $summary !== '' ? $summary : 'Account status is currently unclear.';
        }

        $data['executive_insights'] = $this->normalizeStringArray($data['executive_insights'] ?? []);
        if (count($data['executive_insights']) === 0) {
            $data['executive_insights'] = $insights;
        }
        $data['executive_insights'] = array_slice($data['executive_insights'], 0, 5);
        if (count($data['executive_insights']) < 3) {
            $data['executive_insights'] = array_pad($data['executive_insights'], 3, 'Not available');
        }

        $data['key_relationships'] = is_array($data['key_relationships'] ?? null) ? $data['key_relationships'] : [];
        if (count($data['key_relationships']) === 0) {
            $data['key_relationships'] = [['name' => 'Not available', 'role' => 'Stakeholder', 'strength' => 'Medium']];
        }

        // Handle AI returning combined 'risks_and_opportunities' key instead of separate keys
        if (empty($data['key_risks']) && empty($data['growth_opportunities'])) {
            $combined = $data['risks_and_opportunities'] ?? null;
            if (is_array($combined)) {
                // Log the actual structure to see what keys the AI used
                Log::debug('[ReportSkill] risks_and_opportunities raw structure', [
                    'keys' => array_keys($combined),
                    'raw' => json_encode($combined),
                ]);

                // Try all known key variants
                $data['key_risks'] = $combined['risks'] ?? $combined['key_risks'] ?? $combined['risk'] ?? $combined['Risks'] ?? [];
                $data['growth_opportunities'] = $combined['opportunities'] ?? $combined['growth_opportunities'] ?? $combined['opportunity'] ?? $combined['Opportunities'] ?? [];

                // If still empty, treat as flat list and split in half
                if (count((array) $data['key_risks']) === 0 && count((array) $data['growth_opportunities']) === 0) {
                    $flatItems = array_values(array_filter($combined, 'is_string'));
                    $half = (int) ceil(count($flatItems) / 2);
                    $data['key_risks'] = array_slice($flatItems, 0, $half);
                    $data['growth_opportunities'] = array_slice($flatItems, $half);
                }

                Log::debug('[ReportSkill] Extracted from risks_and_opportunities', [
                    'risks_count' => count((array) $data['key_risks']),
                    'opps_count' => count((array) $data['growth_opportunities']),
                ]);
            } elseif ($combined !== null) {
                Log::warning('[ReportSkill] risks_and_opportunities not an array', [
                    'type' => gettype($combined),
                    'value' => json_encode($combined),
                ]);
            }
        }

        $data['key_risks'] = $this->normalizeStringArray($data['key_risks'] ?? []);
        if (count($data['key_risks']) === 0) {
            $data['key_risks'] = [];
        }

        $data['growth_opportunities'] = $this->normalizeStringArray($data['growth_opportunities'] ?? []);
        if (count($data['growth_opportunities']) === 0) {
            $data['growth_opportunities'] = [];
        }

        // Only use values if the AI explicitly provided them — empty string means hide the signal, not show a generic fallback
        $data['usage_signal'] = trim((string) ($data['usage_signal'] ?? ''));
        $data['support_signal'] = trim((string) ($data['support_signal'] ?? ''));
        $data['sentiment_signal'] = trim((string) ($data['sentiment_signal'] ?? ''));
        $data['engagement_pattern'] = trim((string) ($data['engagement_pattern'] ?? ''));

        $roleActions = is_array($data['role_based_actions'] ?? null) ? $data['role_based_actions'] : [];
        $data['role_based_actions'] = [
            'sales' => $this->normalizeStringArray($roleActions['sales'] ?? []),
            'csm' => $this->normalizeStringArray($roleActions['csm'] ?? []),
            'support' => $this->normalizeStringArray($roleActions['support'] ?? []),
            'exec_sponsor' => $this->normalizeStringArray($roleActions['exec_sponsor'] ?? []),
        ];

        if (count($data['role_based_actions']['sales']) === 0) {
            $data['role_based_actions']['sales'] = $actions;
        }
        if (count($data['role_based_actions']['csm']) === 0) {
            $data['role_based_actions']['csm'] = ['Track relationship health signals before changing customer plan.'];
        }
        if (count($data['role_based_actions']['support']) === 0) {
            $data['role_based_actions']['support'] = ['Review product or delivery blockers referenced in the thread.'];
        }
        if (count($data['role_based_actions']['exec_sponsor']) === 0) {
            $data['role_based_actions']['exec_sponsor'] = ['Monitor relationship for senior sponsorship opportunities.'];
        }

        return $data;
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn ($item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }

    private function normalizeEnum(mixed $value, array $allowed, string $fallback): string
    {
        $normalized = trim((string) $value);

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        return $fallback;
    }

    private function normalizeStatus(array $data): string
    {
        $statusValue = $this->normalizeEnum(
            $data['status_value'] ?? null,
            ['Strategic', 'Growth', 'At Risk', 'Stable'],
            ''
        );

        if ($statusValue !== '') {
            return $statusValue;
        }

        $accountStatusText = strtolower(trim((string) ($data['account_status'] ?? '')));
        if (str_contains($accountStatusText, 'strategic')) {
            return 'Strategic';
        }
        if (str_contains($accountStatusText, 'growth')) {
            return 'Growth';
        }
        if (str_contains($accountStatusText, 'risk') || str_contains($accountStatusText, 'at risk')) {
            return 'At Risk';
        }
        if (str_contains($accountStatusText, 'stable')) {
            return 'Stable';
        }

        return 'Stable';
    }
}
