/**
 * mockAiData.ts
 * Provides structured AI insights for the Conversations UI (Phase 1).
 */

export interface AiTriageResult {
    id?: number;
    email_thread_id?: number;
    summary: string;
    intent: 'sales' | 'support' | 'billing' | 'partnership' | 'spam' | 'general' | 'follow_up';
    intent_confidence: number;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    suggested_status: string;
    low_risk_resolution: boolean;
    suggested_follow_up: boolean; // Add for new badge
    success_probability: number;
    behavioral_pulse: 'heating_up' | 'cooling_down' | 'stable'; // Phase 4: Engagement trend
    strategic_action: {
        goal: string;
        reason: string;
        recommendation: string;
    }; // Phase 4: Proactive suggest
}

export interface AiMemoryTask {
    id: number;
    title: string;
    description?: string | null;
    priority?: string | null;
    is_completed: boolean;
    due_at?: string | null;
    completed_at?: string | null;
}

export interface AiMemorySummary {
    relationship_summary: string;
    sentiment: 'positive' | 'neutral' | 'frustrated';
    open_loops: string[];
    last_commitment: string;
    contact_id?: number;
    relationship_strength?: string;
    memory_points?: string[];
    tasks?: AiMemoryTask[];
}

export interface AiDraft {
    subject: string;
    body: string;
}

const DEFAULT_TRIAGE: AiTriageResult = {
    summary: 'AI triage is currently unavailable. You can continue manually for this conversation.',
    intent: 'general',
    intent_confidence: 0,
    priority: 'medium',
    suggested_status: 'Pending',
    low_risk_resolution: false,
    suggested_follow_up: false,
    success_probability: 0,
    behavioral_pulse: 'stable',
    strategic_action: {
        goal: 'continue_conversation',
        reason: 'ai_unavailable_or_missing_context',
        recommendation: 'Proceed with a concise manual follow-up and re-run AI analysis later.',
    },
};

export const normalizeBehavioralPulse = (
    value?: string | null
): 'heating_up' | 'cooling_down' | 'stable' => {
    if (value === 'heating_up' || value === 'cooling_down' || value === 'stable') {
        return value;
    }

    if (value === 'neutral') {
        return 'stable';
    }

    return 'stable';
};

export const deriveSuggestedStatus = (priority?: string | null): string => {
    const normalized = (priority ?? '').toLowerCase();
    return normalized === 'high' || normalized === 'urgent' ? 'Open' : 'Pending';
};

export const adaptTriageFromApi = (payload: unknown): AiTriageResult => {
    if (!payload || typeof payload !== 'object') {
        return DEFAULT_TRIAGE;
    }

    const data = payload as Record<string, unknown>;
    const priority = String(data.priority ?? 'medium').toLowerCase();
    const strategicAction =
        data.strategic_action && typeof data.strategic_action === 'object'
            ? (data.strategic_action as Record<string, unknown>)
            : {};
    const intentValue = String(data.intent ?? 'general');
    const intent: AiTriageResult['intent'] = (
        ['sales', 'support', 'billing', 'partnership', 'spam', 'general', 'follow_up'].includes(intentValue)
            ? intentValue
            : 'general'
    ) as AiTriageResult['intent'];

    return {
        id: typeof data.id === 'number' ? data.id : undefined,
        email_thread_id: typeof data.email_thread_id === 'number' ? data.email_thread_id : undefined,
        summary: String(data.summary ?? DEFAULT_TRIAGE.summary),
        intent,
        intent_confidence: Number(data.intent_confidence ?? 0),
        priority: (['low', 'medium', 'high', 'urgent'].includes(priority) ? priority : 'medium') as AiTriageResult['priority'],
        suggested_status: String(data.suggested_status ?? deriveSuggestedStatus(priority)),
        low_risk_resolution: Boolean(data.low_risk_resolution ?? false),
        suggested_follow_up: Boolean(data.suggested_follow_up ?? false),
        success_probability: Number(data.success_probability ?? 0),
        behavioral_pulse: normalizeBehavioralPulse(String(data.behavioral_pulse ?? '')),
        strategic_action: {
            goal: String(strategicAction.goal ?? DEFAULT_TRIAGE.strategic_action.goal),
            reason: String(strategicAction.reason ?? DEFAULT_TRIAGE.strategic_action.reason),
            recommendation: String(strategicAction.recommendation ?? DEFAULT_TRIAGE.strategic_action.recommendation),
        },
    };
};

export const createFallbackMemory = (summary?: string): AiMemorySummary => ({
    relationship_summary: summary ?? 'No relationship memory is available yet for this contact.',
    sentiment: 'neutral',
    open_loops: [],
    last_commitment: '',
    memory_points: [],
    tasks: [],
});

export const adaptMemoryFromApi = (payload: unknown): AiMemorySummary => {
    if (!payload || typeof payload !== 'object') {
        return createFallbackMemory();
    }

    const data = payload as Record<string, unknown>;

    const tasks: AiMemoryTask[] = Array.isArray(data.tasks)
        ? data.tasks.map((task) => {
              const taskData = task && typeof task === 'object' ? (task as Record<string, unknown>) : {};
              return {
                  id: Number(taskData.id ?? 0),
                  title: String(taskData.title ?? 'Untitled task'),
                  description: typeof taskData.description === 'string' ? taskData.description : null,
                  priority: typeof taskData.priority === 'string' ? taskData.priority : null,
                  is_completed: Boolean(taskData.is_completed),
                  due_at: typeof taskData.due_at === 'string' ? taskData.due_at : null,
                  completed_at: typeof taskData.completed_at === 'string' ? taskData.completed_at : null,
              };
          })
        : [];

    const memoryPoints = Array.isArray(data.memory_points)
        ? data.memory_points.map((point: unknown) => String(point))
        : [];

    const relationshipStrength = String(data.relationship_strength ?? 'neutral').toLowerCase();
    const sentiment: AiMemorySummary['sentiment'] =
        relationshipStrength === 'strong'
            ? 'positive'
            : relationshipStrength === 'weak'
            ? 'frustrated'
            : 'neutral';

    return {
        contact_id: typeof data.contact_id === 'number' ? data.contact_id : undefined,
        relationship_summary: String(data.relationship_summary ?? 'No relationship summary available.'),
        relationship_strength: String(data.relationship_strength ?? 'moderate'),
        memory_points: memoryPoints,
        tasks,
        sentiment,
        open_loops: tasks.map((task) => task.title),
        last_commitment: memoryPoints[0] ?? '',
    };
};
