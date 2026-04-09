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
        $userPrompt = $this->promptFactory->buildUserPrompt($thread);

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
        $validated = $this->enforceRevivalLogic($validated, $metadata, $previousTriage);
        $validated = $this->enforceEscalationLogic($validated, $metadata, $previousTriage);

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
            $metadata['repair_type'] = $metadata['repair_type'] 
                ? $metadata['repair_type'] . ',terminal_override' 
                : 'terminal_override';
        }

        // 2. Action Suppression for Damaged Relationships
        if (($data['relationship_health'] ?? '') === 'damaged' && !str_starts_with($data['strategic_action_json']['recommendation'] ?? '', 'Tasks:')) {
            $data['strategic_action_json']['recommendation'] = 'Tasks: Review hostile sentiment and archive if necessary.';
            $metadata['repair_applied'] = true;
            $metadata['repair_type'] = $metadata['repair_type'] 
                ? $metadata['repair_type'] . ',action_suppression' 
                : 'action_suppression';
        }

        return $data;
    }

    private function enforceRevivalLogic(array $data, array &$metadata, ?AiTriageResult $previousTriage): array
    {
        if ($previousTriage === null) {
            return $data;
        }

        // closed_lost → reopened: clamp probability, force act_now and heating_up
        if (
            ($previousTriage->thread_state ?? '') === 'closed_lost' &&
            ($data['thread_state'] ?? '') === 'reopened'
        ) {
            $data['success_probability'] = max(25, min(45, $data['success_probability'] ?? 35));
            $data['actionability']       = 'act_now';
            $data['behavioral_pulse']    = 'heating_up';
            // Note: priority is left for the AI to decide — not forced here
            $metadata['repair_applied'] = true;
            $metadata['repair_type']    = $metadata['repair_type']
                ? $metadata['repair_type'] . ',revival_override'
                : 'revival_override';
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
            $metadata['repair_type']     = $metadata['repair_type']
                ? $metadata['repair_type'] . ',escalation_guard'
                : 'escalation_guard';
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
            $metadata['repair_type'] = $metadata['repair_type'] 
                ? $metadata['repair_type'] . ',urgency_downgrade' 
                : 'urgency_downgrade';
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
}

