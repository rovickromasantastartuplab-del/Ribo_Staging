<?php

namespace App\Services\AI\Skills;

use App\Models\Contact;
use App\Models\Lead;
use App\Services\AI\Prompts\MemoryPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class MemorySkill
{
    public function __construct(
        private readonly MemoryPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function summarize(Contact|Lead $entity, array $config, array $triageContext = []): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($entity, $triageContext);

        $raw = $this->provider->summarizeMemory($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'contact_name' => $entity->name,
            'contact_email' => $entity->email,
            'prompt_version' => MemoryPromptFactory::VERSION,
        ]);

        $metadata = [
            'prompt_version' => MemoryPromptFactory::VERSION,
            'validation_stage_failed' => null,
            'repair_applied' => false,
            'repair_type' => null,
            'fallback_applied' => false,
            'fallback_reason' => null,
            'prompt_tokens' => (int) ($raw['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($raw['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($raw['total_tokens'] ?? 0),
        ];

        $validated = $this->validateParse($raw, $metadata);
        if (!$metadata['fallback_applied']) {
            $validated = $this->validatePolicy($validated, $metadata);
        }
        if ($metadata['fallback_applied']) {
            $validated = $this->applyRepair($validated, $metadata);
        }

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

        $latestState = $latest['thread_state'] ?? '';
        $latestHealth = $latest['relationship_health'] ?? '';
        $latestPulse = $latest['behavioral_pulse'] ?? '';
        $history = collect($triageContext);

        if ($latestState === 'closed_lost' || $latestHealth === 'damaged') {
            $data['relationship_strength'] = 'weak';
            $data['relationship_summary'] = $latestState === 'closed_lost'
                ? 'Latest triage marks the relationship as closed_lost despite any earlier positive history.'
                : 'Latest triage marks the relationship as damaged and requiring careful handling.';
        } elseif ($latestState === 'reopened' || $latestState === 'stalled') {
            if ($data['relationship_strength'] === 'strong') {
                $data['relationship_strength'] = 'moderate';
            }

            if ($latestState === 'reopened') {
                $data['relationship_summary'] = 'Relationship recently reopened after a previously lost thread, but renewed momentum is still cautious.';
            }

            if ($latestState === 'stalled') {
                $data['relationship_summary'] = 'Recent relationship momentum is stalled and should be treated as paused until business motion returns.';
            }
        }

        if ($latestPulse === 'broken') {
            $data['memory_points_json'][] = 'Most recent thread ended with broken engagement.';
        }

        $frictionCount = $history->filter(
            fn ($entry) => in_array($entry['thread_state'] ?? '', ['objection', 'misaligned'], true)
                || in_array($entry['relationship_health'] ?? '', ['strained', 'damaged'], true)
        )->count();

        if ($frictionCount >= 2) {
            $data['memory_points_json'][] = 'Recent triage history shows repeated friction and unresolved objections.';
        }

        $hasPriorClosedLost = $history
            ->reject(fn (array $entry): bool => (bool) ($entry['is_latest'] ?? false))
            ->contains(fn (array $entry): bool => ($entry['thread_state'] ?? '') === 'closed_lost');

        if ($latestState === 'reopened' && $hasPriorClosedLost) {
            $data['memory_points_json'][] = 'The relationship reopened after a previously lost thread, so renewed momentum should be treated cautiously.';
        }

        if ($latestState === 'stalled') {
            $data['memory_points_json'][] = 'Recent engagement is stalled and needs a fresh signal before it should be treated as active momentum.';
        }

        $healthyCount = $history->filter(
            fn ($entry) => ($entry['thread_state'] ?? '') === 'active'
                && in_array($entry['relationship_health'] ?? '', ['positive'], true)
        )->count();

        if ($healthyCount >= 2 && count($triageContext) >= 2) {
            $data['memory_points_json'][] = 'Recent threads show consistent engagement and healthy momentum.';
        }

        $data['memory_points_json'] = collect($data['memory_points_json'] ?? [])
            ->map(static fn ($point): string => trim((string) $point))
            ->filter(static fn (string $point): bool => $point !== '')
            ->unique()
            ->values()
            ->all();

        return $data;
    }
}
