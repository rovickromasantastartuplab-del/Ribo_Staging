<?php

namespace App\Services\AI\Skills;

use App\Models\AiTriageResult;
use App\Models\EmailThread;
use App\Services\AI\Prompts\TriagePromptFactory;
use App\Services\AI\Providers\OpenAiConversationClient;

class TriageSkill
{
    private const MODULE_PREFIXES = ['Tasks', 'Quotes', 'Meetings', 'Contacts', 'Leads', 'Invoices'];

    private const MODULE_KEYWORDS = [
        'Tasks' => ['task', 'follow up', 'remind', 'assign', 'review', 'investigate', 'research', 'fix', 'bug', 'issue'],
        'Quotes' => ['quote', 'proposal', 'pricing', 'price', 'cost', 'estimate', 'send proposal'],
        'Meetings' => ['meeting', 'demo', 'call', 'sync', 'walkthrough', 'propose', 'schedule', 'book'],
        'Contacts' => ['contact', 'stakeholder', 'participant', 'member', 'owner', 'profile'],
        'Leads' => ['lead', 'qualify', 'stage', 'engaged', 'nurture', 'convert'],
        'Invoices' => ['invoice', 'billing', 'payment', 'paid', 'unpaid', 'finance', 'receipt'],
    ];

    public function __construct(
        private readonly TriagePromptFactory $promptFactory,
        private readonly OpenAiConversationClient $provider
    ) {
    }

    public function analyze(EmailThread $thread, array $config, ?AiTriageResult $previousTriage = null): array
    {
        $systemPrompt = $this->promptFactory->buildSystemPrompt();
        $userPrompt = $this->promptFactory->buildUserPrompt($thread, $previousTriage);

        $rawResponse = $this->provider->analyzeTriage($config, [
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userPrompt,
            'thread_subject' => $thread->subject,
            'prompt_version' => TriagePromptFactory::VERSION,
        ]);

        $metadata = [
            'prompt_version'           => TriagePromptFactory::VERSION,
            'original_recommendation'  => $rawResponse['strategic_action_json']['recommendation'] ?? '',
            'validation_stage_failed'  => null,
            'repair_applied'           => false,
            'repair_type'              => null,
            'fallback_applied'         => false,
            'fallback_reason'          => null,
        ];

        $validated = $this->validateParse($rawResponse, $metadata);

        if (!$metadata['fallback_applied']) {
            $validated = $this->validatePolicy($validated, $metadata);
        }

        if ($metadata['fallback_applied']) {
            $validated = $this->applyRepair($validated, $metadata);
        }

        // Final Safety Gate: Terminal Overrides & Urgency Enforcement
        $validated = $this->enforceTerminalLogic($validated, $metadata);
        $validated = $this->gateUrgency($validated, $metadata);

        // State-transition enforcement using previous triage
        $validated = $this->enforceRevivalLogic($validated, $metadata, $previousTriage, $thread);
        $validated = $this->enforceActivePromotionLogic($validated, $metadata, $previousTriage, $thread);
        $validated = $this->enforceEscalationLogic($validated, $metadata, $previousTriage);
        $validated = $this->enforceStateCalibration($validated, $metadata);

        return [
            'result'   => array_merge($validated, ['prompt_version' => TriagePromptFactory::VERSION]),
            'metadata' => $metadata,
        ];
    }

    private function validateParse(array $data, array &$metadata): array
    {
        $requiredKeys = [
            'summary', 'intent', 'priority', 'strategic_action_json', 
            'thread_state', 'relationship_health', 'actionability'
        ];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key]) || (is_string($data[$key]) && trim($data[$key]) === '')) {
                $metadata['validation_stage_failed'] = 'parse';
                $metadata['fallback_applied'] = true;
                $metadata['fallback_reason'] = "missing_required_key_{$key}";
                return $data;
            }
        }

        return $data;
    }

    private function enforceTerminalLogic(array $data, array &$metadata): array
    {
        // 1. Force Probability & Recommendation for Lost Deals
        if (($data['thread_state'] ?? '') === 'closed_lost') {
            $data['success_probability'] = min($data['success_probability'] ?? 0, 5);
            $data['behavioral_pulse'] = 'broken';
            $data['priority'] = 'low';
            
            // Override recommendation to prevent "Sales Optimism"
            $data['strategic_action_json']['recommendation'] = 'Tasks: Archive thread and mark as lost opportunity.';
            $data['strategic_action_json']['goal'] = 'Cease interaction';
            
            $metadata['repair_applied'] = true;
            $this->appendRepairType($metadata, 'terminal_override');
        }

        // 2. Action Suppression for Damaged Relationships
        if (($data['relationship_health'] ?? '') === 'damaged' && !str_starts_with($data['strategic_action_json']['recommendation'] ?? '', 'Tasks:')) {
            $data['strategic_action_json']['recommendation'] = 'Tasks: Review hostile sentiment and archive if necessary.';
            $metadata['repair_applied'] = true;
            $this->appendRepairType($metadata, 'action_suppression');
        }

        return $data;
    }

    private function enforceRevivalLogic(array $data, array &$metadata, ?AiTriageResult $previousTriage, EmailThread $thread): array
    {
        if ($previousTriage === null) {
            return $data;
        }

        $previousState = $previousTriage->thread_state ?? '';
        $currentState  = $data['thread_state'] ?? '';

        // Guard: any transition OUT of closed_lost must have an inbound trigger
        if ($previousState === 'closed_lost' && $currentState !== 'closed_lost') {
            $latestSenderRole = $this->detectLatestSenderRole($thread);

            if ($latestSenderRole === 'outbound_team') {
                // Outbound message cannot revive a closed_lost thread under any state label
                $data['thread_state']    = 'closed_lost';
                $data['actionability']   = 'monitor';
                $data['success_probability'] = min($data['success_probability'] ?? 5, 5);
                $data['behavioral_pulse'] = 'broken';
                $data['strategic_action_json']['goal']           = 'Wait for explicit revival';
                $data['strategic_action_json']['reason']         = 'Only an inbound customer/prospect reply can revive a previously lost thread.';
                $data['strategic_action_json']['recommendation'] = 'Tasks: Wait for explicit inbound customer/prospect confirmation before treating this thread as revived.';

                $metadata['repair_applied'] = true;
                $this->appendRepairType($metadata, 'outbound_recovery_guard');

                return $data;
            }

            // Inbound revival path: allow and calibrate
            // Only allow reopened as the transition state (inbound doesn't jump directly to active)
            if ($currentState !== 'reopened') {
                $data['thread_state'] = 'reopened';
            }

            $data['success_probability'] = max(25, min(45, $data['success_probability'] ?? 35));
            $data['actionability']       = 'act_now';

            // Only force heating_up if probability is high enough to be "hot"
            if (($data['success_probability'] ?? 0) > 30) {
                $data['behavioral_pulse'] = 'heating_up';
            } else {
                $data['behavioral_pulse'] = 'stable';
            }

            $metadata['repair_applied'] = true;
            $this->appendRepairType($metadata, 'revival_override');
        }

        return $data;
    }

    private function enforceActivePromotionLogic(array $data, array &$metadata, ?AiTriageResult $previousTriage, EmailThread $thread): array
    {
        if ($previousTriage === null) {
            return $data;
        }

        $currentState = $data['thread_state'] ?? '';
        $previousState = $previousTriage->thread_state ?? '';
        $snippet = strtolower($thread->getAttribute('snippet') ?? '');

        // Reopened -> Active Promotion: if we are in reopened and see concrete business progression
        if ($previousState === 'reopened' && $currentState === 'reopened') {
            $businessSignals = [
                'send over', 'proposal', 'timing', 'schedule', 'quote', 
                'pricing', 'deliverable', 'first-pass', 'approach', 'next step',
                'review request', 'let\'s move', 'forward'
            ];

            foreach ($businessSignals as $signal) {
                if (str_contains($snippet, $signal)) {
                    $data['thread_state'] = 'active';
                    $data['success_probability'] = max(35, min(55, $data['success_probability'] ?? 45));
                    $data['behavioral_pulse'] = 'heating_up';
                    
                    $metadata['repair_applied'] = true;
                    $this->appendRepairType($metadata, 'active_promotion');
                    break;
                }
            }
        }

        return $data;
    }

    private function enforceEscalationLogic(array $data, array &$metadata, ?AiTriageResult $previousTriage): array
    {
        if ($previousTriage === null) {
            return $data;
        }

        $currentState  = $data['thread_state'] ?? '';
        $previousProb  = (int) ($previousTriage->success_probability ?? 0);
        $currentProb   = (int) ($data['success_probability'] ?? 0);

        // Objection or misaligned: probability must not increase while state remains stuck
        if (
            in_array($currentState, ['objection', 'misaligned'], true) &&
            $currentProb > $previousProb
        ) {
            $data['success_probability'] = $previousProb;
            $metadata['repair_applied']  = true;
            $this->appendRepairType($metadata, 'escalation_guard');
        }

        return $data;
    }

    private function enforceStateCalibration(array $data, array &$metadata): array
    {
        $threadState = $data['thread_state'] ?? '';
        $actionability = $data['actionability'] ?? '';
        $recommendation = (string) ($data['strategic_action_json']['recommendation'] ?? '');

        if ($threadState === 'objection') {
            $changed = false;

            if (($data['success_probability'] ?? 0) > 55) {
                $data['success_probability'] = 55;
                $changed = true;
            }

            if (($data['behavioral_pulse'] ?? '') === 'heating_up') {
                $data['behavioral_pulse'] = 'cooling_down';
                $changed = true;
            }

            if (!str_starts_with($recommendation, 'Tasks:')) {
                $data['strategic_action_json']['goal'] = 'Resolve stated objection';
                $data['strategic_action_json']['reason'] = 'The thread is blocked by an explicit concern that must be handled before any commercial advance.';
                $data['strategic_action_json']['recommendation'] = 'Tasks: Address the objection directly before proposing any meeting or commercial next step.';
                $changed = true;
            }

            if ($changed) {
                $metadata['repair_applied'] = true;
                $this->appendRepairType($metadata, 'objection_guard');
            }
        }

        if ($threadState === 'misaligned') {
            $changed = false;

            if (($data['success_probability'] ?? 0) > 30) {
                $data['success_probability'] = 30;
                $changed = true;
            }

            if (in_array($data['relationship_health'] ?? '', ['positive', 'neutral'], true)) {
                $data['relationship_health'] = 'strained';
                $changed = true;
            }

            if (($data['relationship_health'] ?? '') === 'damaged') {
                if (($data['behavioral_pulse'] ?? '') !== 'broken') {
                    $data['behavioral_pulse'] = 'broken';
                    $changed = true;
                }
            } elseif (($data['behavioral_pulse'] ?? '') !== 'cooling_down') {
                $data['behavioral_pulse'] = 'cooling_down';
                $changed = true;
            }

            if (!str_starts_with($recommendation, 'Tasks:')) {
                $data['strategic_action_json']['goal'] = 'Repair or confirm mismatch';
                $data['strategic_action_json']['reason'] = 'The thread shows a deeper scope, value, or process mismatch that should be clarified before any commercial ask.';
                $data['strategic_action_json']['recommendation'] = 'Tasks: Clarify the scope, value gap, or process mismatch before proposing any meeting or quote.';
                $changed = true;
            }

            if ($changed) {
                $metadata['repair_applied'] = true;
                $this->appendRepairType($metadata, 'misalignment_guard');
            }
        }

        if (
            in_array($actionability, ['archive', 'do_not_pursue'], true) &&
            !str_starts_with((string) ($data['strategic_action_json']['recommendation'] ?? ''), 'Tasks:')
        ) {
            $data['strategic_action_json']['goal'] = 'Respect non-pursuit state';
            $data['strategic_action_json']['reason'] = 'The actionability state forbids active commercial pursuit.';
            $data['strategic_action_json']['recommendation'] = 'Tasks: Archive or hold the thread according to triage actionability.';
            $metadata['repair_applied'] = true;
            $this->appendRepairType($metadata, 'actionability_guard');
        }

        return $data;
    }

    private function validatePolicy(array $data, array &$metadata): array
    {
        $recommendation = (string) ($data['strategic_action_json']['recommendation'] ?? '');
        
        // 1. Regex Prefix Check
        $pattern = '/^(' . implode('|', self::MODULE_PREFIXES) . '):/i';
        if (!preg_match($pattern, $recommendation, $matches)) {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'invalid_module_prefix';
            return $data;
        }

        $module = ucfirst(strtolower($matches[1]));
        $action = trim(substr($recommendation, strlen($matches[0])));

        // 2. Semantic Keyword Check
        $keywords = self::MODULE_KEYWORDS[$module] ?? [];
        $found = false;
        foreach ($keywords as $keyword) {
            if (stripos($action, $keyword) !== false) {
                $found = true;
                break;
            }
        }

        if (!$found && $action !== '') {
            $metadata['validation_stage_failed'] = 'policy';
            $metadata['fallback_applied'] = true;
            $metadata['fallback_reason'] = 'semantic_module_mismatch';
            return $data;
        }

        return $data;
    }

    private function gateUrgency(array $data, array &$metadata): array
    {
        // Urgency Gating (Safety Check) - Intent must be high-signal for Urgent
        if ($data['priority'] === 'urgent' && !in_array($data['intent'], ['sales', 'billing'], true)) {
            $data['priority'] = 'high';
            $metadata['repair_applied'] = true;
            $this->appendRepairType($metadata, 'urgency_downgrade');
        }

        return $data;
    }

    private function applyRepair(array $data, array &$metadata): array
    {
        $intent = $data['intent'] ?? 'general';
        
        $fallbacks = [
            'sales' => 'Leads: Review sales opportunity manually.',
            'billing' => 'Invoices: Review billing thread manually.',
            'partnership' => 'Meetings: Review partnership inquiry manually.',
            'follow_up' => 'Tasks: Review follow-up opportunity manually.',
            'default' => 'Tasks: Review and respond manually.',
        ];

        $data['strategic_action_json']['recommendation'] = $fallbacks[$intent] ?? $fallbacks['default'];
        
        // Hard caps for confidence during fallback
        $data['intent_confidence'] = min($data['intent_confidence'] ?? 60, 60);
        $data['success_probability'] = min($data['success_probability'] ?? 50, 50);

        return $data;
    }

    private function detectLatestSenderRole(EmailThread $thread): string
    {
        $ownEmail = $this->getOwnEmailAddress($thread);
        $latestSender = $this->getLatestMessageSenderEmail($thread);

        if ($ownEmail === null || $latestSender === null) {
            return 'unknown';
        }

        return $latestSender === $ownEmail
            ? 'outbound_team'
            : 'inbound_customer_prospect';
    }

    private function getOwnEmailAddress(EmailThread $thread): ?string
    {
        try {
            $account = $thread->getAttribute('gmailAccount');
            $email = strtolower(trim((string) ($account->gmail_address ?? '')));

            return $email !== '' ? $email : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getLatestMessageSenderEmail(EmailThread $thread): ?string
    {
        try {
            $message = $thread->getAttribute('latestMessage');
            $email = strtolower(trim((string) ($message->from_email ?? '')));

            return $email !== '' ? $email : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function appendRepairType(array &$metadata, string $type): void
    {
        $metadata['repair_type'] = $metadata['repair_type']
            ? $metadata['repair_type'] . ',' . $type
            : $type;
    }
}

