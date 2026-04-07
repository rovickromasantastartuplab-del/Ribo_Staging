import React from 'react';
import { Sparkles, ChevronRight, ChevronLeft, Target, Activity } from 'lucide-react';
import { cn } from '@/lib/utils';
import ConversationAiPanel from './ConversationAiPanel';
import { getMockTriage } from '../utils/mockAiData';

interface AiStrategyDrawerProps {
    threadId: number | null;
    isOpen: boolean;
    onToggle: () => void;
    onInsertDraft: (content: string) => void;
}

export default function AiStrategyDrawer({ threadId, isOpen, onToggle, onInsertDraft }: AiStrategyDrawerProps) {
    // Get triage data for the slim bar preview
    const triage = threadId ? getMockTriage(threadId) : null;

    if (!threadId) {
        return (
            <div 
                className={cn(
                    "h-full border-r bg-slate-50/10 dark:bg-slate-900/10 transition-all duration-300 ease-in-out flex flex-col shrink-0 overflow-hidden items-center pt-8",
                    isOpen ? "w-[320px]" : "w-[52px]"
                )}
            >
               <Sparkles className="w-5 h-5 text-slate-300" />
            </div>
        );
    }

    return (
        <div 
            className={cn(
                "h-full border-r bg-white dark:bg-slate-950 transition-all duration-300 ease-in-out flex flex-col shrink-0 overflow-hidden relative group shadow-sm",
                isOpen ? "w-[320px]" : "w-[52px]"
            )}
        >
            {/* Toggle Button (Floating) */}
            <button
                onClick={onToggle}
                className="absolute right-1.5 top-4 z-20 h-6 w-6 rounded-full bg-white dark:bg-slate-800 border shadow-md flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:scale-110 opacity-0 group-hover:opacity-100 transition-all cursor-pointer"
            >
                {isOpen ? <ChevronLeft className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
            </button>

            {!isOpen ? (
                /* Slim Bar (Collapsed State) */
                <div 
                    className="flex flex-col items-center py-6 gap-10 h-full cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors"
                    onClick={onToggle}
                >
                    <div className="p-2 rounded-xl bg-indigo-500/10 mb-2">
                        <Sparkles className="w-5 h-5 text-indigo-500 animate-pulse" />
                    </div>
                    
                    <div className="flex flex-col items-center gap-1.5 px-1">
                        <Target className="w-4 h-4 text-indigo-500" />
                        <span className="text-[9px] font-black text-indigo-600 tracking-tighter">
                            {triage ? `${triage.success_probability}%` : '--'}
                        </span>
                    </div>

                    <div className="flex flex-col items-center gap-1.5 px-1">
                        <div className="relative">
                           <Activity className={cn(
                                "w-4 h-4",
                                triage?.behavioral_pulse === 'heating_up' ? "text-orange-500" : "text-slate-400"
                            )} />
                            {triage?.behavioral_pulse === 'heating_up' && (
                                <span className="absolute -top-1 -right-1 flex h-2 w-2">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="mt-auto pb-6 opacity-30 group-hover:opacity-100 transition-opacity">
                         <div className="w-1 h-12 rounded-full bg-indigo-500/20" />
                    </div>
                </div>
            ) : (
                /* Full Panel (Expanded State) */
                <div className="w-[320px] h-full animate-in fade-in slide-in-from-left-2 duration-300">
                    <ConversationAiPanel 
                        threadId={threadId} 
                        onInsertDraft={onInsertDraft} 
                    />
                </div>
            )}
        </div>
    );
}
