import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import AiTriageCard from './AiTriageCard';
import AiMemoryCard from './AiMemoryCard';
import AiStrategicActionCard from './AiStrategicActionCard';
import {
    AiMemorySummary,
    AiTriageResult,
    adaptMemoryFromApi,
    adaptTriageFromApi,
    createFallbackMemory,
    derivePulseInfo,
} from '../utils/mockAiData';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { RefreshCcw, Sparkles, Target, Activity } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';

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

    const resolveContactId = (threadPayload: unknown): number | null => {
        if (!threadPayload || typeof threadPayload !== 'object') {
            return null;
        }

        const payload = threadPayload as Record<string, unknown>;
        const rootThread = payload.thread;
        const thread = rootThread && typeof rootThread === 'object'
            ? (rootThread as Record<string, unknown>)
            : payload;
        const contacts = thread.contacts;

        if (!Array.isArray(contacts) || contacts.length === 0) {
            return null;
        }

        const firstContact = contacts[0];
        if (!firstContact || typeof firstContact !== 'object') {
            return null;
        }

        const contactId = (firstContact as Record<string, unknown>).id;
        return typeof contactId === 'number' ? contactId : null;
    };

    const loadAiInsights = useCallback(async (showRefreshToast = false) => {
        setLoading(true);
        setIsUnavailable(false);

        try {
            const triageResponse = await axios.get(`/ai/triage/${threadId}`);
            const triageData = adaptTriageFromApi(triageResponse.data?.data);
            setTriage(triageData);

            let memoryData: AiMemorySummary = createFallbackMemory('No contact linked to this thread yet.');

            try {
                const threadRoute =
                    typeof route === 'function'
                        ? route('api.conversations.show', threadId)
                        : `/api/conversations/${threadId}`;
                const threadResponse = await axios.get(threadRoute, { params: { per_page: 1 } });
                const contactId = resolveContactId(threadResponse.data);

                if (contactId) {
                    const memoryResponse = await axios.get(`/ai/memory/${contactId}`);
                    memoryData = adaptMemoryFromApi(memoryResponse.data?.data);
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
                toast[unavailable ? 'warning' : 'error'](
                    unavailable ? 'AI is currently unavailable.' : 'Failed to refresh AI insights.'
                );
            }
        } finally {
            setLoading(false);
        }
    }, [threadId]);

    useEffect(() => {
        void loadAiInsights(false);
    }, [loadAiInsights]);

    if (loading) {
        return (
            <div className="p-4 space-y-4">
                <div className="flex items-center gap-3 mb-6">
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
        <div className="h-full flex flex-col bg-slate-50/30 dark:bg-slate-950/30">
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm sticky top-0 z-10">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                        <Sparkles className="w-5 h-5 text-indigo-500 animate-pulse" />
                        <h2 className="font-bold text-slate-800 dark:text-slate-100">AI Assistant</h2>
                        {isUnavailable && (
                            <Badge variant="outline" className="text-[10px] bg-amber-500/10 text-amber-700 border-amber-500/20">
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
                        <RefreshCcw className="w-4 h-4" />
                    </Button>
                </div>

                <div className="flex gap-2">
                    <div className="flex-1 p-2 rounded-lg bg-indigo-500/5 border border-indigo-500/10 flex items-center justify-between">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <Target className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                            <span className="text-[10px] font-bold uppercase text-slate-500 truncate tracking-tight">Deal Prob.</span>
                        </div>
                        <span className="text-sm font-bold text-indigo-600 ml-2">{triage?.success_probability ?? 0}%</span>
                    </div>
                    <div className="flex-1 p-2 rounded-lg bg-slate-500/5 border border-slate-500/10 flex items-center justify-between">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <Activity className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span className="text-[10px] font-bold uppercase text-slate-500 truncate tracking-tight">Mood</span>
                        </div>
                        <Badge
                            variant="outline"
                            className={`text-[10px] h-5 border-none ${
                                derivePulseInfo(
                                    triage?.behavioral_pulse ?? 'stable',
                                    triage?.success_probability ?? 0,
                                    triage?.thread_state ?? 'active'
                                ).className
                            }`}
                        >
                            {
                                derivePulseInfo(
                                    triage?.behavioral_pulse ?? 'stable',
                                    triage?.success_probability ?? 0,
                                    triage?.thread_state ?? 'active'
                                ).label
                            }
                        </Badge>
                    </div>
                </div>
            </div>

            <ScrollArea className="flex-1">
                <div className="p-4 space-y-4 pb-20">
                    <AiStrategicActionCard data={triage?.strategic_action ?? null} />

                    <AiTriageCard data={triage ?? adaptTriageFromApi(null)} />

                    <AiMemoryCard data={memory ?? createFallbackMemory()} triageData={triage ?? undefined} />
                </div>
            </ScrollArea>
        </div>
    );
}
