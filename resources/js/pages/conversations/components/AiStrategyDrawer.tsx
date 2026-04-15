import { cn } from '@/lib/utils';
import axios from 'axios';
import { Activity, ChevronLeft, ChevronRight, Sparkles, Target } from 'lucide-react';
import { useEffect, useState } from 'react';
import { adaptTriageFromApi, AiTriageResult, derivePulseInfo, deriveStateInfo } from '../utils/mockAiData';
import ConversationAiPanel from './ConversationAiPanel';

interface AiStrategyDrawerProps {
    threadId: number | null;
    isOpen: boolean;
    onToggle: () => void;
    onInsertDraft: (content: string) => void;
}

export default function AiStrategyDrawer({ threadId, isOpen, onToggle, onInsertDraft }: AiStrategyDrawerProps) {
    const [triage, setTriage] = useState<AiTriageResult | null>(null);
    const pulseInfo = triage ? derivePulseInfo(triage.behavioral_pulse, triage.success_probability, triage.thread_state) : null;
    const stateInfo = triage ? deriveStateInfo(triage.thread_state) : null;

    useEffect(() => {
        if (!threadId) {
            setTriage(null);
            return;
        }

        let isCurrent = true;
        axios
            .get(`/ai/triage/${threadId}`)
            .then((response) => {
                if (!isCurrent) {
                    return;
                }
                setTriage(adaptTriageFromApi(response.data?.data));
            })
            .catch(() => {
                if (!isCurrent) {
                    return;
                }
                setTriage(null);
            });

        return () => {
            isCurrent = false;
        };
    }, [threadId]);

    if (!threadId) {
        return (
            <div
                className={cn(
                    'flex h-full shrink-0 flex-col items-center overflow-hidden border-l bg-slate-50/10 py-6 transition-all duration-300 ease-in-out dark:bg-slate-900/10',
                    isOpen ? 'w-[320px]' : 'w-[52px]',
                )}
            >
                <Sparkles className="h-5 w-5 text-slate-300" />
            </div>
        );
    }

    return (
        <div
            className={cn(
                'group relative flex h-full shrink-0 flex-col overflow-hidden border-l bg-white shadow-sm transition-all duration-300 ease-in-out dark:bg-slate-950',
                isOpen ? 'w-[320px]' : 'w-[52px]',
            )}
        >
            {/* Toggle Button (Floating) */}
            <button
                onClick={onToggle}
                className="absolute top-4 left-1.5 z-20 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full border bg-white text-slate-400 opacity-0 shadow-md transition-all group-hover:opacity-100 hover:scale-110 hover:text-indigo-500 dark:bg-slate-800"
            >
                {isOpen ? <ChevronRight className="h-3.5 w-3.5" /> : <ChevronLeft className="h-3.5 w-3.5" />}
            </button>

            {!isOpen ? (
                /* Slim Bar (Collapsed State) */
                <div
                    className="flex h-full cursor-pointer flex-col items-center gap-10 py-6 transition-colors hover:bg-slate-50 dark:hover:bg-slate-900"
                    onClick={onToggle}
                >
                    <div className="mb-2 rounded-xl bg-indigo-500/10 p-2">
                        <Sparkles className="h-5 w-5 animate-pulse text-indigo-500" />
                    </div>

                    <div className="flex flex-col items-center gap-1.5 px-1">
                        <Target className="h-4 w-4 text-indigo-500" />
                        <span className="text-[9px] font-black tracking-tighter text-indigo-600">
                            {triage ? `${triage.success_probability}%` : '--'}
                        </span>
                    </div>

                    <div className="flex flex-col items-center gap-1.5 px-1">
                        <div className="relative">
                            <Activity
                                className={cn(
                                    'h-4 w-4',
                                    pulseInfo?.className.includes('red-')
                                        ? 'text-red-500'
                                        : triage?.behavioral_pulse === 'heating_up'
                                          ? 'text-orange-500'
                                          : triage?.behavioral_pulse === 'cooling_down'
                                            ? 'text-blue-500'
                                            : 'text-slate-400',
                                )}
                            />
                            {triage?.behavioral_pulse === 'heating_up' && (
                                <span className="absolute -top-1 -right-1 flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75"></span>
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-orange-500"></span>
                                </span>
                            )}
                        </div>
                        {stateInfo && (
                            <span className="max-w-full truncate text-[8px] font-bold tracking-tight text-slate-500 uppercase">
                                {stateInfo.label}
                            </span>
                        )}
                    </div>

                    <div className="mt-auto pb-6 opacity-30 transition-opacity group-hover:opacity-100">
                        <div className="h-12 w-1 rounded-full bg-indigo-500/20" />
                    </div>
                </div>
            ) : (
                /* Full Panel (Expanded State) */
                <div className="animate-in fade-in slide-in-from-right-2 h-full w-[320px] duration-300">
                    <ConversationAiPanel threadId={threadId} onInsertDraft={onInsertDraft} />
                </div>
            )}
        </div>
    );
}
