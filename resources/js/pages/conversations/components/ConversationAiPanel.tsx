import { useState, useEffect } from 'react';
import AiTriageCard from './AiTriageCard';
import AiMemoryCard from './AiMemoryCard';
import AiReplyAssistantCard from './AiReplyAssistantCard';
import { getMockTriage, getMockMemory } from '../utils/mockAiData';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { RefreshCcw, Sparkles, Target, Activity } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';

interface ConversationAiPanelProps {
    threadId: number;
    onInsertDraft: (content: string) => void;
    onAiConvert?: () => void;
}

export default function ConversationAiPanel({ threadId, onInsertDraft, onAiConvert }: ConversationAiPanelProps) {
    const [loading, setLoading] = useState(true);
    const [triage, setTriage] = useState<any>(null);
    const [memory, setMemory] = useState<any>(null);

    const loadAiInsights = () => {
        setLoading(true);
        // Simulate backend latency
        setTimeout(() => {
            setTriage(getMockTriage(threadId));
            setMemory(getMockMemory(threadId));
            setLoading(false);
        }, 1000);
    };

    useEffect(() => {
        loadAiInsights();
    }, [threadId]);

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
            {/* Header / Intelligence Strip */}
            <div className="p-4 border-b border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm sticky top-0 z-10">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                        <Sparkles className="w-5 h-5 text-indigo-500 animate-pulse" />
                        <h2 className="font-bold text-slate-800 dark:text-slate-100">AI Sidekick</h2>
                    </div>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        className="h-8 w-8 text-slate-400 hover:text-indigo-500"
                        onClick={() => {
                            loadAiInsights();
                            toast.info("Re-analyzing conversation flow...");
                        }}
                    >
                        <RefreshCcw className="w-4 h-4" />
                    </Button>
                </div>

                {/* Phase 4 Intelligence Strip */}
                <div className="flex gap-2">
                    <div className="flex-1 p-2 rounded-lg bg-indigo-500/5 border border-indigo-500/10 flex items-center justify-between">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <Target className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                            <span className="text-[10px] font-bold uppercase text-slate-500 truncate tracking-tight">Deal Prob.</span>
                        </div>
                        <span className="text-sm font-bold text-indigo-600 ml-2">{triage.success_probability}%</span>
                    </div>
                    <div className="flex-1 p-2 rounded-lg bg-slate-500/5 border border-slate-500/10 flex items-center justify-between">
                        <div className="flex items-center gap-1.5 min-w-0">
                            <Activity className="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span className="text-[10px] font-bold uppercase text-slate-500 truncate tracking-tight">Pulse</span>
                        </div>
                        <Badge variant="outline" className={`text-[10px] h-5 border-none ${triage.behavioral_pulse === 'heating_up' ? 'bg-orange-500/10 text-orange-600' : 'bg-slate-500/10 text-slate-600'}`}>
                            {triage.behavioral_pulse === 'heating_up' ? '🔥 HOT' : 'STABLE'}
                        </Badge>
                    </div>
                </div>
            </div>

            <ScrollArea className="flex-1">
                <div className="p-4 space-y-4 pb-20">
                    {/* Approach 3: Stratety is embedded in Reply Assistant, which sits at top of content list */}
                    <AiReplyAssistantCard 
                        triageData={triage} 
                        onInsertDraft={onInsertDraft} 
                    />

                    <AiTriageCard 
                        data={triage} 
                        onAiConvert={onAiConvert}
                    />
                    
                    <AiMemoryCard 
                        data={memory} 
                        triageData={triage} 
                    />
                </div>
            </ScrollArea>
        </div>
    );
}
