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

export interface AiOpportunity {
    id: number;
    name: string;
    value: number;
    stage: string;
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

export const getMockTriage = (threadId: number): AiTriageResult => {
    // Deterministic mock data based on threadId
    const intents: AiTriageResult['intent'][] = ['sales', 'support', 'billing', 'spam'];
    const intent = intents[threadId % intents.length];
    
    return {
        intent,
        intent_confidence: 85 + (threadId % 10),
        priority: threadId % 3 === 0 ? 'high' : 'medium',
        summary: intent === 'sales' 
            ? "Customer is asking about enterprise pricing and a demo for a team of 50. They seem interested but are comparing with competitors."
            : "Customer is reporting a bug in the dashboard where data doesn't refresh automatically.",
        suggested_status: intent === 'sales' ? 'Open' : 'Pending',
        low_risk_resolution: intent === 'spam',
        suggested_follow_up: threadId % 4 === 0, // Mock follow-up suggest
        success_probability: intent === 'sales' ? 75 + (threadId % 20) : 40,
        behavioral_pulse: intent === 'sales' ? 'heating_up' : 'stable',
        strategic_action: {
            goal: intent === 'sales' ? "Win Enterprise Deal" : "Resolve Technical Issue",
            reason: intent === 'sales' ? "Competitive interest detected" : "UX friction causing frustration",
            recommendation: intent === 'sales' 
                ? "Offer a 10% discount to overcome price sensitivity and schedule a demo now."
                : "Acknowledge the bug and provide a direct timeline for the fix."
        }
    };
};

const mockMemoryData: Record<number, AiMemorySummary> = {
    0: {
        relationship_summary: "Frequent contact from Acme Corp. Previously discussed API integrations. Relationship is warm.",
        sentiment: 'positive',
        open_loops: [
            "Provide updated API documentation",
            "Follow up on demo scheduling"
        ],
        last_commitment: "Promised to send demo link by tomorrow morning."
    }
};

const mockDrafts: Record<string, AiDraft> = {
    'professional': {
        subject: "Re: Enterprise Pricing Inquiry",
        body: "<p>Hello,</p><p>Thank you for reaching out! To overcome any price sensitivity, I'd like to offer you a <strong>10% limited-time discount</strong> on our Enterprise tier. This plan includes dedicated support and advanced security features for your team of 50.</p><p>Would you like to schedule a 15-minute demo this Thursday? I can walk your team through the dashboard then.</p><p>Best regards,</p>"
    },
    'friendly': {
        subject: "Re: Checking out Ribo",
        body: "<p>Hey there!</p><p>Great to hear from you. It sounds like Ribo would be a perfect fit for your team of 50. Since I want to make sure you get the best deal, I've applied a <strong>10% discount</strong> to your account for the first year.</p><p>I'd love to show you how it works in action. Do you have a few minutes for a quick chat later this week? Let me know!</p><p>Cheers,</p>"
    },
    'concise': {
        subject: "Ribo Enterprise Quote",
        body: "<p>Hi, based on your team size (50), I can offer a 10% discount on the Enterprise plan. Are you available for a brief demo this week to finalize?</p><p>Best,</p>"
    }
};

export const getMockMemory = (threadId: number): AiMemorySummary => {
    return mockMemoryData[threadId] || mockMemoryData[0];
};

export const getMockDraft = (tone: string): AiDraft => {
    return mockDrafts[tone] || mockDrafts['professional'];
};

export interface ScrapedLead {
    name: string;
    email: string;
    company: string;
    phone: string;
    estimated_value: number;
    industry_id?: number;
    source_id?: number;
}

export const getMockScrapedLead = (threadId: number): ScrapedLead => {
    // Deterministic mock based on threadId % 3
    const index = threadId % 3;
    const mocks: ScrapedLead[] = [
        {
            name: "John Carter",
            email: "j.carter@acmecorp.com",
            company: "Acme Corp",
            phone: "+1 (555) 902-1234",
            estimated_value: 15000,
            industry_id: 2, // Technology
            source_id: 3 // Email/Sync
        },
        {
            name: "Sarah Jenkins",
            email: "sjenkins@devsystems.io",
            company: "DevSystems.io",
            phone: "+44 20 7946 0123",
            estimated_value: 4500,
            industry_id: 5, // SaaS
            source_id: 3
        },
        {
            name: "Michael Chen",
            email: "mchen@globalogistics.net",
            company: "Global Logistics Net",
            phone: "+65 6743 1234",
            estimated_value: 28000,
            industry_id: 8, // Logistics
            source_id: 3
        }
    ];

    return mocks[index];
};

export const getMockOpportunities = (_threadId: number): AiOpportunity[] => {
    void _threadId;
    return [
        { id: 1, name: "Enterprise License - FY26", value: 45000, stage: "Discovery" },
        { id: 2, name: "Custom API Integration", value: 12500, stage: "Proposal" },
        { id: 3, name: "Consulting & Onboarding", value: 5000, stage: "Closed-Won" }
    ];
};

export const getMockNarrativeStory = (_id: number | string): string => {
    void _id;
    return `
        <div class="space-y-4">
            <p>The journey with this account began 3 months ago with a high-intent inquiry regarding <strong>data scalability</strong>. After an initial discovery call, the technical team identified a significant friction point in their legacy system.</p>
            <p>The engagement peaked last week when the AI detected a shift in stakeholder sentiment from <em>"Cautious"</em> to <em>"Urgent"</em>, prompting a strategic follow-up with a 15% incentive.</p>
            <p>The deal is currently in the <strong>final negotiation phase</strong>, with high probability of closing by month-end.</p>
        </div>
    `.trim();
};
