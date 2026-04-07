import React, { useEffect, useState } from 'react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { AiTriageCard } from './AiTriageCard';
import { cn } from '@/lib/utils';
import { AiMemoryCard } from './AiMemoryCard';
import { AiReplyAssistantCard } from './AiReplyAssistantCard';
import { getMockTriage, getMockMemory, AiTriageResult, AiMemorySummary } from '../utils/mockAiData';
import { Loader2, Sparkles, BrainCircuit } from 'lucide-react';
import { toast } from '@/components/custom-toast';

interface ConversationAiPanelProps {
    threadId?: number;
    onInsertDraft: (body: string) => void;
}

export const ConversationAiPanel: React.FC<ConversationAiPanelProps> = ({ threadId, onInsertDraft }) => {
    const [loading, setLoading] = useState(false);
    const [triage, setTriage] = useState<AiTriageResult | null>(null);
    const [memory, setMemory] = useState<AiMemorySummary | null>(null);

    useEffect(() => {
        if (!threadId) return;
        //hi
        setLoading(true);
        // Simulate background processing for AI
        const timer = setTimeout(() => {
            setTriage(getMockTriage(threadId));
            setMemory(getMockMemory(threadId));
            setLoading(false);
        }, 800);

        return () => clearTimeout(timer);
    }, [threadId]);

    const handleApplyTriage = (type: string) => {
        toast.success(`Successfully applied AI ${type} suggestion!`);
    };

    if (!threadId) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-8 text-center text-muted-foreground space-y-4 opacity-50">
                <BrainCircuit className="h-12 w-12 text-muted-foreground/30 stroke-1" />
                <p className="text-sm">Select a conversation to see AI insights and relationship memory.</p>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center h-full p-12 text-center text-primary/40 space-y-4">
                <div className="relative">
                    <Loader2 className="h-10 w-10 animate-spin" />
                    <Sparkles className="h-4 w-4 absolute -top-1 -right-1 animate-pulse text-primary" />
                </div>
                <div className="space-y-1">
                    <p className="text-sm font-bold uppercase tracking-widest animate-pulse">Analyzing Thread...</p>
                    <p className="text-[10px] text-muted-foreground">Fetching CRM context and relationship history</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-full">
            <div className="px-4 py-3 border-b flex items-center justify-between bg-muted/10 shrink-0">
                <div className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-primary" />
                    <h3 className="text-xs font-bold uppercase tracking-wider text-foreground">AI Sidekick</h3>
                </div>
                <Button 
                    variant="ghost" 
                    size="icon" 
                    className="h-7 w-7 text-muted-foreground hover:text-primary transition-colors"
                    onClick={() => {
                        setLoading(true);
                        setTimeout(() => {
                            setTriage(getMockTriage(threadId));
                            setMemory(getMockMemory(threadId));
                            setLoading(false);
                            toast.success('Analysis refreshed');
                        }, 1200);
                    }}
                >
                    <Loader2 className={cn("h-3.5 w-3.5", loading && "animate-spin")} />
                </Button>
            </div>
            
            <ScrollArea className="flex-1">
                <div className="p-4 space-y-2">
                {triage && (
                    <AiTriageCard 
                        data={triage} 
                        onApply={handleApplyTriage} 
                    />
                )}
                
                {memory && (
                    <AiMemoryCard 
                        data={memory} 
                    />
                )}

                <AiReplyAssistantCard 
                    onInsertDraft={onInsertDraft}
                />
                
                <div className="pt-6 pb-4 text-center">
                    <p className="text-[10px] text-muted-foreground/60 flex items-center justify-center gap-1">
                        <BrainCircuit className="h-2.5 w-2.5" />
                        AI Insights are powered by Ribo IntelliSync
                    </p>
                </div>
            </div>
            </ScrollArea>
        </div>
    );
};
