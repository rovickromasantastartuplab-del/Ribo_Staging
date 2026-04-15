import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Info, Zap } from 'lucide-react';
import { AiTriageResult } from '../utils/mockAiData';

interface AiStrategicActionCardProps {
    data: {
        goal: string;
        reason: string;
        recommendation: string;
    } | null;
    triageData?: AiTriageResult;
}

export default function AiStrategicActionCard({ data }: AiStrategicActionCardProps) {
    if (!data) return null;

    return (
        <Card className="overflow-hidden border-none bg-gradient-to-br from-indigo-50 to-white shadow-sm ring-1 ring-indigo-500/10 dark:from-indigo-950/20 dark:to-slate-900/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 px-4 pt-4 pb-2">
                <div className="flex items-center gap-2">
                    <div className="rounded-md bg-indigo-500/10 p-1.5">
                        <Zap className="h-3.5 w-3.5 fill-current text-indigo-500" />
                    </div>
                    <CardTitle className="text-[11px] font-black tracking-widest text-indigo-600 uppercase dark:text-indigo-400">
                        AI Suggested Action
                    </CardTitle>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="rounded p-0.5 text-slate-400 hover:text-indigo-500"
                                aria-label="AI Suggested Action data source"
                            >
                                <Info className="h-3.5 w-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-xs text-[11px] leading-relaxed">
                            Based on this thread&apos;s message flow plus linked CRM context from the triage analysis.
                        </TooltipContent>
                    </Tooltip>
                </div>
            </CardHeader>
            <CardContent className="space-y-3 px-4 pt-1 pb-4">
                <div className="space-y-1.5">
                    <p className="text-sm leading-snug font-bold text-slate-900 italic dark:text-slate-100">"{data.recommendation}"</p>
                    <div className="flex items-start gap-1.5 rounded-lg border border-slate-100 bg-white/50 p-2 dark:border-slate-800 dark:bg-slate-900/50">
                        <Info className="mt-0.5 h-3 w-3 shrink-0 text-slate-400" />
                        <span className="text-[10px] font-medium text-slate-500">
                            <strong className="text-slate-700 dark:text-slate-300">Goal:</strong> {data.goal} - {data.reason}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
