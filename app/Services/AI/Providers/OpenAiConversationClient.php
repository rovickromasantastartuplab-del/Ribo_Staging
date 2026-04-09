<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiConversationClient
{
    public function generateDraft(array $config, array $payload): array
    {
        $systemPrompt = (string) ($payload['system_prompt'] ?? '');
        $userPrompt = (string) ($payload['user_prompt'] ?? '');
        $threadSubject = trim((string) ($payload['thread_subject'] ?? ''));
        $defaultPromptVersion = trim((string) ($payload['prompt_version'] ?? 'draft-v2'));
        if ($defaultPromptVersion === '') {
            $defaultPromptVersion = 'draft-v2';
        }

        $response = $this->requestJson($config, $systemPrompt, $userPrompt, $this->draftResponseSchema());
        $data = $response['data'];

        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $threadSubject !== '' ? "Re: {$threadSubject}" : 'Follow-up';
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('AI unavailable');
        }

        $promptVersion = trim((string) ($data['prompt_version'] ?? $defaultPromptVersion));
        if ($promptVersion === '') {
            $promptVersion = $defaultPromptVersion;
        }

        $usage = $response['usage'];
        return [
            'subject' => $subject,
            'body' => $body,
            'prompt_version' => $promptVersion,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }

    public function analyzeTriage(array $config, array $payload): array
    {
        $defaultPromptVersion = trim((string) ($payload['prompt_version'] ?? 'triage-v2'));
        if ($defaultPromptVersion === '') {
            $defaultPromptVersion = 'triage-v2';
        }

        $response = $this->requestJson(
            $config,
            (string) ($payload['system_prompt'] ?? ''),
            (string) ($payload['user_prompt'] ?? ''),
            $this->triageResponseSchema()
        );
        $data = $response['data'];

        $intent = strtolower(trim((string) ($data['intent'] ?? 'general')));
        if (!in_array($intent, ['sales', 'support', 'billing', 'partnership', 'spam', 'general', 'follow_up'], true)) {
            $intent = 'general';
        }

        $priority = strtolower(trim((string) ($data['priority'] ?? 'medium')));
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $behavioralPulse = strtolower(trim((string) ($data['behavioral_pulse'] ?? 'stable')));
        if ($behavioralPulse === 'neutral') {
            $behavioralPulse = 'stable';
        }
        if (!in_array($behavioralPulse, ['heating_up', 'cooling_down', 'stable', 'broken'], true)) {
            $behavioralPulse = 'stable';
        }

        $threadState = strtolower(trim((string) ($data['thread_state'] ?? 'active')));
        $relationshipHealth = strtolower(trim((string) ($data['relationship_health'] ?? 'neutral')));
        $actionability = strtolower(trim((string) ($data['actionability'] ?? 'monitor')));

        $strategicAction = $data['strategic_action'] ?? [];
        if (!is_array($strategicAction)) {
            $strategicAction = [];
        }

        $promptVersion = trim((string) ($data['prompt_version'] ?? $defaultPromptVersion));
        if ($promptVersion === '') {
            $promptVersion = $defaultPromptVersion;
        }

        return [
            'intent' => $intent,
            'intent_confidence' => $this->clampPercentage((int) ($data['intent_confidence'] ?? 0)),
            'priority' => $priority,
            'thread_state' => $threadState,
            'relationship_health' => $relationshipHealth,
            'actionability' => $actionability,
            'success_probability' => $this->clampPercentage((int) ($data['success_probability'] ?? 0)),
            'behavioral_pulse' => $behavioralPulse,
            'summary' => trim((string) ($data['summary'] ?? '')),
            'strategic_action_json' => [
                'goal' => trim((string) ($strategicAction['goal'] ?? 'continue_conversation')),
                'reason' => trim((string) ($strategicAction['reason'] ?? 'insufficient_context')),
                'recommendation' => trim((string) ($strategicAction['recommendation'] ?? 'Send a concise follow-up with next steps.')),
            ],
            'prompt_version' => $promptVersion,
        ];
    }

    public function summarizeMemory(array $config, array $payload): array
    {
        $defaultPromptVersion = trim((string) ($payload['prompt_version'] ?? 'memory-v2'));
        if ($defaultPromptVersion === '') {
            $defaultPromptVersion = 'memory-v2';
        }

        $response = $this->requestJson(
            $config,
            (string) ($payload['system_prompt'] ?? ''),
            (string) ($payload['user_prompt'] ?? ''),
            $this->memoryResponseSchema()
        );
        $data = $response['data'];
        $memoryPoints = $data['memory_points'] ?? [];
        if (!is_array($memoryPoints)) {
            $memoryPoints = [];
        }

        $relationshipStrength = strtolower(trim((string) ($data['relationship_strength'] ?? 'moderate')));
        if (!in_array($relationshipStrength, ['weak', 'moderate', 'strong'], true)) {
            $relationshipStrength = 'moderate';
        }

        $promptVersion = trim((string) ($data['prompt_version'] ?? $defaultPromptVersion));
        if ($promptVersion === '') {
            $promptVersion = $defaultPromptVersion;
        }

        return [
            'relationship_summary' => trim((string) ($data['relationship_summary'] ?? 'No relationship summary available.')),
            'relationship_strength' => $relationshipStrength,
            'memory_points_json' => collect($memoryPoints)
                ->map(static fn ($point): string => trim((string) $point))
                ->filter(static fn (string $point): bool => $point !== '')
                ->values()
                ->all(),
            'prompt_version' => $promptVersion,
        ];
    }

    public function generateReport(array $config, array $payload): array
    {
        $defaultPromptVersion = trim((string) ($payload['prompt_version'] ?? 'report-v2'));
        if ($defaultPromptVersion === '') {
            $defaultPromptVersion = 'report-v2';
        }

        $response = $this->requestJson(
            $config,
            (string) ($payload['system_prompt'] ?? ''),
            (string) ($payload['user_prompt'] ?? ''),
            $this->reportResponseSchema()
        );
        $data = $response['data'];

        $promptVersion = trim((string) ($data['prompt_version'] ?? $defaultPromptVersion));
        if ($promptVersion === '') {
            $promptVersion = $defaultPromptVersion;
        }

        return [
            'summary' => trim((string) ($data['summary'] ?? 'Report unavailable.')),
            'key_insights' => $this->normalizeStringList($data['key_insights'] ?? []),
            'next_actions' => $this->normalizeStringList($data['next_actions'] ?? []),
            'prompt_version' => $promptVersion,
        ];
    }

    private function requestJson(array $config, string $systemPrompt, string $userPrompt, ?array $responseSchema = null): array
    {
        $enabled = (bool) ($config['enabled'] ?? false);
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if (!$enabled || $apiKey === '') {
            throw new RuntimeException('AI unavailable');
        }

        $model = trim((string) ($config['model'] ?? 'gpt-5.4-mini'));
        $timeoutSeconds = max(5, (int) ($config['timeout_seconds'] ?? 30));

        $payload = [
            'model' => $model !== '' ? $model : 'gpt-5.4-mini',
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if (is_array($responseSchema)) {
            $payload['text'] = [
                'format' => array_merge(['type' => 'json_schema'], $responseSchema),
            ];
        }

        $response = Http::timeout($timeoutSeconds)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', $payload);

        if (!$response->successful()) {
            Log::warning('Conversation AI upstream call failed', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
            throw new RuntimeException('AI unavailable');
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('AI unavailable');
        }

        $text = $this->extractTextPayload($json);
        if ($text === '') {
            throw new RuntimeException('AI unavailable');
        }

        return [
            'data' => $this->decodeJsonObject($text),
            'usage' => $this->extractUsage($json),
        ];
    }

    private function extractTextPayload(array $json): string
    {
        $outputText = $json['output_text'] ?? null;
        if (is_string($outputText) && trim($outputText) !== '') {
            return trim($outputText);
        }

        $parts = [];
        $output = $json['output'] ?? null;
        if (is_array($output)) {
            foreach ($output as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $content = $item['content'] ?? null;
                if (!is_array($content)) {
                    continue;
                }
                foreach ($content as $chunk) {
                    if (!is_array($chunk)) {
                        continue;
                    }
                    $text = $chunk['text'] ?? null;
                    if (is_string($text) && trim($text) !== '') {
                        $parts[] = trim($text);
                        continue;
                    }
                    if (is_array($text) && isset($text['value']) && is_string($text['value']) && trim($text['value']) !== '') {
                        $parts[] = trim($text['value']);
                    }
                }
            }
        }

        if (count($parts) > 0) {
            return trim(implode("\n", $parts));
        }

        $choices = $json['choices'] ?? null;
        if (is_array($choices) && isset($choices[0]['message']['content']) && is_string($choices[0]['message']['content'])) {
            return trim($choices[0]['message']['content']);
        }

        return '';
    }

    private function decodeJsonObject(string $text): array
    {
        $normalized = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $normalized, $matches) === 1) {
            $normalized = trim((string) ($matches[1] ?? ''));
        }

        try {
            $decoded = json_decode($normalized, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            $firstBrace = strpos($normalized, '{');
            $lastBrace = strrpos($normalized, '}');
            if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
                throw new RuntimeException('AI unavailable');
            }

            $candidate = substr($normalized, $firstBrace, $lastBrace - $firstBrace + 1);
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                throw new RuntimeException('AI unavailable');
            }
        }
    }

    private function extractUsage(array $json): array
    {
        $usage = $json['usage'] ?? [];
        if (!is_array($usage)) {
            $usage = [];
        }

        $promptTokens = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens));

        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];
    }

    private function normalizeStringList(mixed $value): array
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

    private function clampPercentage(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function draftResponseSchema(): array
    {
        return [
            'name' => 'conversation_ai_draft',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['subject', 'body', 'prompt_version'],
                'properties' => [
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'prompt_version' => ['type' => ['string', 'null']],
                ],
            ],
        ];
    }

    private function triageResponseSchema(): array
    {
        return [
            'name' => 'conversation_ai_triage',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'summary',
                    'intent',
                    'intent_confidence',
                    'priority',
                    'success_probability',
                    'behavioral_pulse',
                    'thread_state',
                    'relationship_health',
                    'actionability',
                    'prompt_version',
                    'strategic_action',
                ],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'intent' => ['type' => 'string'],
                    'intent_confidence' => ['type' => 'integer'],
                    'priority' => ['type' => 'string'],
                    'success_probability' => ['type' => 'integer'],
                    'behavioral_pulse' => ['type' => 'string'],
                    'thread_state' => ['type' => 'string'],
                    'relationship_health' => ['type' => 'string'],
                    'actionability' => ['type' => 'string'],
                    'prompt_version' => ['type' => ['string', 'null']],
                    'strategic_action' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['goal', 'reason', 'recommendation'],
                        'properties' => [
                            'goal' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                            'recommendation' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function memoryResponseSchema(): array
    {
        return [
            'name' => 'conversation_ai_memory',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['relationship_summary', 'relationship_strength', 'memory_points', 'prompt_version'],
                'properties' => [
                    'relationship_summary' => ['type' => 'string'],
                    'relationship_strength' => ['type' => 'string'],
                    'prompt_version' => ['type' => ['string', 'null']],
                    'memory_points' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    private function reportResponseSchema(): array
    {
        return [
            'name' => 'conversation_ai_report',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary', 'key_insights', 'next_actions', 'prompt_version'],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'prompt_version' => ['type' => ['string', 'null']],
                    'key_insights' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'next_actions' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
}
