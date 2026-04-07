/**
 * mockAiData.ts
 * Provides structured AI insights for the Conversations UI (Phase 1).
 */

export interface AiTriageResult {
    summary: string;
    intent: 'sales' | 'support' | 'billing' | 'partnership' | 'spam' | 'general';
    intent_confidence: number;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    suggested_status: string;
    low_risk_resolution: boolean;
    success_probability: number; // Phase 4: Deal prediction
    behavioral_pulse: 'heating_up' | 'cooling_down' | 'stable'; // Phase 4: Engagement trend
    strategic_action: {
        goal: string;
        reason: string;
        recommendation: string;
    }; // Phase 4: Proactive suggest
}

export interface AiMemorySummary {
    relationship_summary: string;
    sentiment: 'positive' | 'neutral' | 'frustrated';
    open_loops: string[];
    last_commitment: string;
}

export interface AiDraft {
    subject: string;
    body: string;
}

export const getMockTriage = (threadId: number): AiTriageResult => {
    // Deterministic mock data based on threadId
    const intents = ['sales', 'support', 'billing', 'spam'] as const;
    const intent = intents[threadId % intents.length] as any;
    
    return {
        intent,
        intent_confidence: 85 + (threadId % 10),
        priority: threadId % 3 === 0 ? 'high' : 'medium',
        summary: intent === 'sales' 
            ? "Customer is asking about enterprise pricing and a demo for a team of 50. They seem interested but are comparing with competitors."
            : "Customer is reporting a bug in the dashboard where data doesn't refresh automatically.",
        suggested_status: intent === 'sales' ? 'Open' : 'Pending',
        low_risk_resolution: intent === 'spam',
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
