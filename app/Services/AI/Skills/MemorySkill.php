<?php

namespace App\Services\AI\Skills;

use App\Models\Contact;
use App\Services\AI\Prompts\MemoryPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class MemorySkill
{
    public function __construct(
        private readonly MemoryPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function summarize(Contact $contact, array $config, array $triageContext = []): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt   = $this->promptFactory->buildUserPrompt($contact, $triageContext);

        $raw = $this->provider->summarizeMemory($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'contact_name' => $contact->name,
            'contact_email' => $contact->email,
            'prompt_version' => MemoryPromptFactory::VERSION,
        ]);

        $metadata = [
            'prompt_version' => MemoryPromptFactory::VERSION,
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

        // Reconcile memory output with validated triage history
        if (!$metadata['fallback_applied'] && !empty($triageContext)) {
            $validated = $this->reconcileWithTriage($validated, $triageContext);
        }

        $validated['prompt_version'] = MemoryPromptFactory::VERSION;

        return [
            'result' => $validated,
            'metadata' => $metadata,
        ];
    }

    private function validateParse(array $data, array &$metadata): array
    {
        $requiredKeys = ['relationship_summary', 'relationship_strength', 'memory_points_json'];
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
        $summary = trim((string) ($data['relationship_summary'] ?? ''));
        $strength = trim((string) ($data['relationship_strength'] ?? ''));
        $points = $data['memory_points_json'] ?? [];

        if ($summary === '' || !in_array($strength, ['weak', 'moderate', 'strong'], true) || !is_array($points)) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'invalid_memory_contract';
            return $data;
        }

        $cleanPoints = collect($points)
            ->map(static fn ($point): string => trim((string) $point))
            ->filter(static fn (string $point): bool => $point !== '')
            ->values()
            ->all();

        if (count($cleanPoints) === 0) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'empty_memory_points';
            return $data;
        }

        $data['memory_points_json'] = $cleanPoints;
        return $data;
    }

    private function applyRepair(array $data, array &$metadata): array
    {
        $data['relationship_summary'] = 'Relationship memory needs manual review from recent conversations.';
        $data['relationship_strength'] = 'moderate';
        $data['memory_points_json'] = ['Review recent threads manually for updated relationship signals.'];
        $metadata['repair_applied'] = true;
        $metadata['repair_type'] = $metadata['repair_type']
            ? $metadata['repair_type'] . ',fallback_memory'
            : 'fallback_memory';

        return $data;
    }

    private function reconcileWithTriage(array $data, array $triageContext): array
    {
        $latest = collect($triageContext)->firstWhere('is_latest', true) ?? $triageContext[0] ?? null;

        if ($latest === null) {
            return $data;
        }

        $latestState  = $latest['thread_state'] ?? '';
        $latestHealth = $latest['relationship_health'] ?? '';
        $latestPulse  = $latest['behavioral_pulse'] ?? '';

        // Hard clamps for strong negative signals (latest thread dominates)
        if ($latestState === 'closed_lost' || $latestHealth === 'damaged') {
            $data['relationship_strength'] = 'weak';
        } elseif (in_array($latestState, ['reopened', 'stalled'], true)) {
            if ($data['relationship_strength'] === 'strong') {
                $data['relationship_strength'] = 'moderate';
            }
        }

        // Append broken engagement memory point
        if ($latestPulse === 'broken') {
            $data['memory_points_json'][] = 'Most recent thread ended with broken engagement.';
        }

        // Positive trend: majority active + positive relationship health
        $healthyCount = collect($triageContext)->filter(
            fn($t) => ($t['thread_state'] ?? '') === 'active' &&
                      in_array($t['relationship_health'] ?? '', ['positive'], true)
        )->count();

        if ($healthyCount >= 2 && count($triageContext) >= 2) {
            $data['memory_points_json'][] = 'Recent threads show consistent engagement and healthy momentum.';
        }

        return $data;
    }
}
