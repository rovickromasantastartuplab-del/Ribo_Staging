import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { Activity, RefreshCcw, Sparkles, Target } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    AiMemorySummary,
    AiTriageResult,
    adaptMemoryFromApi,
    adaptTriageFromApi,
    createFallbackMemory,
    deriveHealthInfo,
    derivePulseInfo,
    deriveStateInfo,
} from '../utils/mockAiData';
import AiMemoryCard from './AiMemoryCard';
import AiStrategicActionCard from './AiStrategicActionCard';
import AiTriageCard from './AiTriageCard';

interface ConversationAiPanelProps {
    threadId: number;
    onInsertDraft: (content: string) => void;
}

export default function ConversationAiPanel({ threadId, onInsertDraft: _onInsertDraft }: ConversationAiPanelProps) {
    void _onInsertDraft;

    const [loading, setLoading] = useState(true);
    const [triage, setTriage] = useState<AiTriageResult | null>(null);
    const [memory, setMemory] = useState<AiMemorySummary | null>(null);
    const [isUnavailable, setIsUnavailable] = useState(false);

    const resolveEntity = (threadPayload: unknown): { id: number; type: 'contact' | 'lead' } | null => {
        if (!threadPayload || typeof threadPayload !== 'object') {
            return null;
        }

        const payload = threadPayload as Record<string, unknown>;
        const rootThread = payload.thread;
        const thread = rootThread && typeof rootThread === 'object' ? (rootThread as Record<string, unknown>) : payload;

        // Try contacts first
        const contacts = thread.contacts;
        if (Array.isArray(contacts) && contacts.length > 0) {
            const contactId = (contacts[0] as Record<string, unknown>)?.id;
            if (typeof contactId === 'number') {
                return { id: contactId, type: 'contact' };
            }
        }

        // Fallback to leads
        const leads = thread.leads;
        if (Array.isArray(leads) && leads.length > 0) {
            const leadId = (leads[0] as Record<string, unknown>)?.id;
            if (typeof leadId === 'number') {
                return { id: leadId, type: 'lead' };
            }
        }

        return null;
    };

    const loadAiInsights = useCallback(
        async (showRefreshToast = false) => {
            setLoading(true);
            setIsUnavailable(false);

            try {
                const triageResponse = await axios.get(`/ai/triage/${threadId}`);
                const triageData = adaptTriageFromApi(triageResponse.data?.data);
                setTriage(triageData);

                let memoryData: AiMemorySummary = createFallbackMemory('No contact linked to this thread yet.');

                try {
                    const threadRoute = typeof route === 'function' ? route('api.conversations.show', threadId) : `/api/conversations/${threadId}`;
                    const threadResponse = await axios.get(threadRoute, { params: { per_page: 1 } });
                    const entityInfo = resolveEntity(threadResponse.data);

                    if (entityInfo) {
                        const memoryResponse = await axios.get(`/ai/memory/${entityInfo.id}`, {
                            params: { entity_type: entityInfo.type }
                        });
                        memoryData = adaptMemoryFromApi(memoryResponse.data?.data, triageData);
                    }
                } catch (error: unknown) {
                    if (axios.isAxiosError(error) && error.response?.status === 422) {
                        setIsUnavailable(true);
                        memoryData = createFallbackMemory('AI memory is temporarily unavailable.');
                    }
                }

                setMemory(memoryData);

                if (showRefreshToast) {
                    toast.success('AI insights refreshed.');
                }
            } catch (error: unknown) {
                const unavailable = axios.isAxiosError(error) && error.response?.status === 422;
                setIsUnavailable(unavailable);
                setTriage(adaptTriageFromApi(null));
                setMemory(createFallbackMemory('AI insights are temporarily unavailable.'));

                if (showRefreshToast) {
                    toast[unavailable ? 'warning' : 'error'](unavailable ? 'AI is currently unavailable.' : 'Failed to refresh AI insights.');
                }
            } finally {
                setLoading(false);
            }
        },
        [threadId],
    );

    useEffect(() => {
        void loadAiInsights(false);
    }, [loadAiInsights]);

    const resolvedTriage = triage ?? adaptTriageFromApi(null);
    const stateInfo = deriveStateInfo(resolvedTriage.thread_state);
    const healthInfo = deriveHealthInfo(resolvedTriage.relationship_health);
    const pulseInfo = derivePulseInfo(resolvedTriage.behavioral_pulse, resolvedTriage.success_probability, resolvedTriage.thread_state);

    if (loading) {
        return (
            <div className="space-y-4 p-4">
                <div className="mb-6 flex items-center gap-3">
                    <Skeleton className="h-10 w-10 rounded-lg" />
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-3 w-32" />
                    </div>
                </div>
                <Skeleton className="h-[200px] w-full rounded-xl" />
                <Skeleton className="h-[150px] w-full rounded-xl" />
                <Skeleton className="h-[300px] w-full rounded-xl" />
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col bg-slate-50/30 dark:bg-slate-950/30">
            <div className="sticky top-0 z-10 border-b border-slate-200 bg-white/50 p-4 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/50">
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Sparkles className="h-5 w-5 animate-pulse text-indigo-500" />
                        <h2 className="font-bold text-slate-800 dark:text-slate-100">AI Assistant</h2>
                        {isUnavailable && (
                            <Badge variant="outline" className="border-amber-500/20 bg-amber-500/10 text-[10px] text-amber-700">
                                Unavailable
                            </Badge>
                        )}
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-slate-400 hover:text-indigo-500"
                        onClick={() => {
                            void loadAiInsights(true);
                            toast.info('Re-analyzing conversation flow...');
                        }}
                    >
                        <RefreshCcw className="h-4 w-4" />
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-lg border border-slate-200/80 bg-white/70 p-2 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="mb-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase">Thread State</div>
                        <Badge variant="outline" className={`h-5 border ${stateInfo.className}`}>
                            {stateInfo.label}
                        </Badge>
                    </div>
                    <div className="rounded-lg border border-slate-200/80 bg-white/70 p-2 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="mb-1 text-[10px] font-bold tracking-tight text-slate-500 uppercase">Relationship</div>
                        <Badge variant="outline" className={`h-5 border ${healthInfo.className}`}>
                            {healthInfo.label}
                        </Badge>
                    </div>
                    <div className="rounded-lg border border-indigo-500/10 bg-indigo-500/5 p-2">
                        <div className="mb-1 flex items-center gap-1.5 text-[10px] font-bold tracking-tight text-slate-500 uppercase">
                            <Target className="h-3.5 w-3.5 shrink-0 text-indigo-500" />
                            Deal Prob.
                        </div>
                        <span className="text-sm font-bold text-indigo-600">{resolvedTriage.success_probability}%</span>
                    </div>
                    <div className="rounded-lg border border-slate-200/80 bg-white/70 p-2 dark:border-slate-800 dark:bg-slate-900/60">
                        <div className="mb-1 flex items-center gap-1.5 text-[10px] font-bold tracking-tight text-slate-500 uppercase">
                            <Activity className="h-3.5 w-3.5 shrink-0 text-slate-500" />
                            Pulse
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className={`h-5 border ${pulseInfo.className}`}>
                                {pulseInfo.label}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <ScrollArea className="flex-1">
                <div className="space-y-4 p-4 pb-20">
                    <AiStrategicActionCard data={resolvedTriage.strategic_action ?? null} triageData={resolvedTriage} />

                    <AiTriageCard data={resolvedTriage} />

                    <AiMemoryCard data={memory ?? createFallbackMemory()} triageData={triage ?? undefined} />
                </div>
            </ScrollArea>
        </div>
    );
}
