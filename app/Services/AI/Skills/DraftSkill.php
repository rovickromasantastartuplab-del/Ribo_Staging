<?php

namespace App\Services\AI\Skills;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Prompts\DraftPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class DraftSkill
{
    private const OPERATOR_ONLY_PATTERNS = [
        'why is this thread closed',
        'why this thread is closed',
        'why is this thread',
        'why thread is',
        'explain this thread',
        'explain why',
        'what is the thread state',
        'what is this thread state',
        'why closed',
        'is this closed',
        'why did ai',
        'what does triage mean',
        'what is actionability',
        'summarize for me',
    ];

    public function __construct(
        private readonly DraftPromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function generate(
        EmailThread $thread,
        string $instruction,
        string $tone,
        array $config,
        ?AiTriageResult $triage = null
    ): array {
        $metadata = [
            'prompt_version'          => DraftPromptFactory::VERSION,
            'validation_stage_failed' => null,
            'repair_applied'          => false,
            'repair_type'             => null,
            'fallback_applied'        => false,
            'fallback_reason'         => null,
            'original_subject'        => '',
            'original_body'           => '',
        ];

        // Run triage guards BEFORE the AI call
        $guardResult = $this->applyTriageGuards($triage, $instruction, $metadata);
        if ($guardResult !== null) {
            return $guardResult;
        }

        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt   = $this->promptFactory->buildUserPrompt($thread, $instruction, $tone, $triage);

        $raw = $this->provider->generateDraft($config, [
            'system_prompt'  => $systemPrompt,
            'user_prompt'    => $userPrompt,
            'thread_subject' => $thread->subject,
            'instruction'    => $instruction,
            'tone'           => $tone,
            'prompt_version' => DraftPromptFactory::VERSION,
        ]);

        $metadata['original_subject'] = $raw['subject'] ?? '';
        $metadata['original_body']    = $raw['body'] ?? '';

        // Set misaligned guard flag if applicable
        if (($triage?->thread_state ?? '') === 'misaligned') {
            $metadata['triage_misaligned_guard'] = true;
        }

        $validated = $this->validateParse($raw, $metadata);
        if (!$metadata['fallback_applied']) {
            $validated = $this->validatePolicy($validated, $metadata, $triage);
        }
        if ($metadata['fallback_applied']) {
            $validated = $this->applyRepair($validated, $metadata, $triage);
        }

        $validated['prompt_version'] = DraftPromptFactory::VERSION;

        return [
            'result'   => $validated,
            'metadata' => $metadata,
        ];
    }

    private function applyTriageGuards(?AiTriageResult $triage, string $instruction, array &$metadata): ?array
    {
        if ($this->isOperatorOnlyInstruction($instruction)) {
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason']  = 'draft_blocked_internal_question';
            return ['result' => ['subject' => '', 'body' => ''], 'metadata' => $metadata];
        }

        if ($triage === null) {
            return null;
        }

        $actionability = $triage->actionability ?? '';
        $threadState   = $triage->thread_state ?? '';

        // Hard block: do_not_pursue — no draft under any circumstance
        if ($actionability === 'do_not_pursue') {
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason']  = 'draft_blocked_do_not_pursue';
            return ['result' => ['subject' => '', 'body' => ''], 'metadata' => $metadata];
        }

        return null;
    }

    private function isOperatorOnlyInstruction(string $instruction): bool
    {
        $normalized = mb_strtolower(trim($instruction));
        if ($normalized === '') {
            return false;
        }

        foreach (self::OPERATOR_ONLY_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        // Generic "why..." question that references system state/AI behavior instead of customer reply intent.
        $isWhyQuestion = str_starts_with($normalized, 'why') || str_contains($normalized, ' why ');
        if (
            $isWhyQuestion
            && (
                str_contains($normalized, 'thread')
                || str_contains($normalized, 'closed')
                || str_contains($normalized, 'triage')
                || str_contains($normalized, 'ai')
                || str_contains($normalized, 'state')
            )
        ) {
            return true;
        }

        return false;
    }

    private function validateParse(array $data, array &$metadata): array
    {
        $requiredKeys = ['subject', 'body'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
                $metadata['validation_stage_failed'] = 'parse';
                $metadata['fallback_applied'] = true;
                $metadata['fallback_reason'] = "missing_required_key_{$key}";
                return $data;
            }
        }

        return $data;
    }

    private function validatePolicy(array $data, array &$metadata, ?AiTriageResult $triage): array
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($subject !== '' && mb_strlen($subject) > 140) {
            $metadata['repair_applied'] = true;
            $metadata['repair_type'] = 'subject_trim';
            $data['subject'] = mb_substr($subject, 0, 140);
        }

        if ($body === '' || stripos($body, '<p>') !== 0) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'invalid_body_format';
            return $data;
        }

        // Keep one clear CTA by avoiding multiple direct questions.
        if (substr_count($body, '?') > 1) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'multiple_cta_questions';
            return $data;
        }

        $normalizedBody = mb_strtolower(strip_tags($body));
        $threadState = (string) ($triage?->thread_state ?? '');
        $relationshipHealth = (string) ($triage?->relationship_health ?? '');

        if (
            $threadState === 'misaligned'
            && $this->containsSchedulingLanguage($normalizedBody)
        ) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'misaligned_scheduling_blocked';
            return $data;
        }

        if (
            in_array($threadState, ['closed_lost'], true) || $relationshipHealth === 'damaged'
        ) {
            if ($this->containsAggressiveSalesLanguage($normalizedBody)) {
                $metadata['validation_stage_failed'] = 'policy';
                $metadata['fallback_applied'] = true;
                $metadata['fallback_reason'] = 'aggressive_sales_blocked';
                return $data;
            }
        }

        return $data;
    }

    private function containsSchedulingLanguage(string $body): bool
    {
        $patterns = [
            'schedule',
            'meeting',
            'book a call',
            'calendar',
            'time slot',
            'demo',
            'jump on a call',
        ];

        return collect($patterns)->contains(fn ($token) => str_contains($body, $token));
    }

    private function containsAggressiveSalesLanguage(string $body): bool
    {
        $patterns = [
            'act now',
            'close this week',
            'limited time',
            'book a demo now',
            'sign today',
            'final push',
            'book a call now',
        ];

        return collect($patterns)->contains(fn ($token) => str_contains($body, $token));
    }

    private function applyRepair(array $data, array &$metadata, ?AiTriageResult $triage = null): array
    {
        $threadState        = $triage?->thread_state ?? '';
        $relationshipHealth = $triage?->relationship_health ?? '';
        $actionability      = $triage?->actionability ?? '';

        $isConstrained = in_array($threadState, ['closed_lost', 'misaligned'], true)
            || $actionability === 'do_not_pursue'
            || $relationshipHealth === 'damaged';

        if ($isConstrained) {
            $data['subject'] = 'Reviewing your message';
            $data['body']    = '<p>Thank you for your message. We are reviewing the details and will be in touch if appropriate.</p>';
        } else {
            $data['subject'] = 'Quick follow-up';
            $data['body']    = '<p>Thanks for the update. Let me know if there is anything I can help clarify.</p>';
        }

        $metadata['repair_applied'] = true;
        $metadata['repair_type']    = $metadata['repair_type']
            ? $metadata['repair_type'] . ',fallback_draft'
            : 'fallback_draft';

        return $data;
    }
}
