<?php

namespace App\Services\AI\Skills;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Prompts\DraftPromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class DraftSkill
{
    private const RECOVERY_KEYWORDS = ['recovery', 'win-back', 'win back', 'closing', 'farewell', 'reopen'];

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
            $validated = $this->validatePolicy($validated, $metadata);
        }
        if ($metadata['fallback_applied']) {
            $validated = $this->applyRepair($validated, $metadata);
        }

        $validated['prompt_version'] = DraftPromptFactory::VERSION;

        return [
            'result'   => $validated,
            'metadata' => $metadata,
        ];
    }

    private function applyTriageGuards(?AiTriageResult $triage, string $instruction, array &$metadata): ?array
    {
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

        // Hard block: closed_lost without explicit recovery intent
        if ($threadState === 'closed_lost') {
            $isRecovery = collect(self::RECOVERY_KEYWORDS)
                ->contains(fn($kw) => stripos($instruction, $kw) !== false);

            if (!$isRecovery) {
                $metadata['fallback_applied'] = true;
                $metadata['fallback_reason']  = 'draft_blocked_terminal_state';
                return ['result' => ['subject' => '', 'body' => ''], 'metadata' => $metadata];
            }
        }

        return null;
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

    private function validatePolicy(array $data, array &$metadata): array
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

        return $data;
    }

    private function applyRepair(array $data, array &$metadata): array
    {
        $data['subject'] = 'Quick follow-up';
        $data['body'] = '<p>Thanks for the update. Would you like us to schedule a quick next step?</p>';
        $metadata['repair_applied'] = true;
        $metadata['repair_type'] = $metadata['repair_type']
            ? $metadata['repair_type'] . ',fallback_draft'
            : 'fallback_draft';

        return $data;
    }
}
