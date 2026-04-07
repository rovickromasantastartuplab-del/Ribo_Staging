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

const mockTriageData: Record<number, AiTriageResult> = {
    // Default mock data for any thread ID
    0: {
        summary: "Customer is asking about enterprise pricing and a demo for a team of 50. They seem interested but are comparing with competitors.",
        intent: 'sales',
        intent_confidence: 94,
        priority: 'high',
        suggested_status: 'Open',
        low_risk_resolution: false,
    }
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
        body: "<p>Hello,</p><p>Thank you for reaching out! I'd be happy to provide more details on our enterprise pricing for a team of 50. We offer custom tiers that include dedicated support and advanced security features.</p><p>Would you like to schedule a 15-minute demo this Thursday? I can walk your team through the dashboard then.</p><p>Best regards,</p>"
    },
    'friendly': {
        subject: "Re: Checking out Ribo",
        body: "<p>Hey there!</p><p>Great to hear from you. It sounds like Ribo would be a perfect fit for your team of 50. Our enterprise plan is specifically built to handle that scale with ease.</p><p>I'd love to show you how it works in action. Do you have a few minutes for a quick chat later this week? Let me know!</p><p>Cheers,</p>"
    }
};

export const getMockTriage = (threadId: number): AiTriageResult => {
    return mockTriageData[threadId] || mockTriageData[0];
};

export const getMockMemory = (threadId: number): AiMemorySummary => {
    return mockMemoryData[threadId] || mockMemoryData[0];
};

export const getMockDraft = (tone: string): AiDraft => {
    return mockDrafts[tone] || mockDrafts['professional'];
};
