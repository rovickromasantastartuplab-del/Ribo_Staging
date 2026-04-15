import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { CheckSquare, History, Info, Square, Zap } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AiMemorySummary, AiTriageResult } from '../utils/mockAiData';

interface AiMemoryCardProps {
    data: AiMemorySummary;
    triageData?: AiTriageResult;
}

export default function AiMemoryCard({ data }: AiMemoryCardProps) {
    const [completedLoops, setCompletedLoops] = useState<string[]>([]);
    const [taskCompletionOverrides, setTaskCompletionOverrides] = useState<Record<number, boolean>>({});
    const [updatingTaskIds, setUpdatingTaskIds] = useState<number[]>([]);

    const toggleLoop = (loop: string) => {
        setCompletedLoops((prev) => (prev.includes(loop) ? prev.filter((item) => item !== loop) : [...prev, loop]));
    };

    const toggleTask = async (taskId: number, currentValue: boolean) => {
        if (updatingTaskIds.includes(taskId)) {
            return;
        }

        setUpdatingTaskIds((prev) => [...prev, taskId]);

        try {
            await axios.patch(`/ai/tasks/${taskId}`, {
                is_completed: !currentValue,
            });
            setTaskCompletionOverrides((prev) => ({
                ...prev,
                [taskId]: !currentValue,
            }));
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                toast.warning('AI is currently unavailable.');
            } else {
                toast.error('Failed to update AI task.');
            }
        } finally {
            setUpdatingTaskIds((prev) => prev.filter((id) => id !== taskId));
        }
    };

    const loops = data.tasks?.length
        ? data.tasks.map((task) => ({
              id: task.id,
              title: task.title,
              isCompleted: task.id in taskCompletionOverrides ? taskCompletionOverrides[task.id] : Boolean(task.is_completed),
          }))
        : data.open_loops.map((loop, idx) => ({
              id: -(idx + 1),
              title: loop,
              isCompleted: completedLoops.includes(loop),
          }));

    return (
        <Card className="border-none bg-gradient-to-br from-white to-slate-50/50 shadow-sm dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3 text-sm font-medium">
                <div className="flex items-center gap-2">
                    <div className="rounded-lg bg-rose-500/10 p-2">
                        <History className="h-4 w-4 text-rose-500" />
                    </div>
                    <CardTitle className="text-sm font-semibold text-slate-900 dark:text-slate-100">Relationship History</CardTitle>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="rounded p-0.5 text-slate-400 hover:text-rose-500"
                                aria-label="Relationship History data source"
                            >
                                <Info className="h-3.5 w-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-xs text-[11px] leading-relaxed">
                            Built from linked contact/lead memory, CRM activity history, and AI task/open-loop tracking for this thread context.
                        </TooltipContent>
                    </Tooltip>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">

                <div className="space-y-1.5">
                    <p className="text-xs leading-relaxed font-medium text-slate-600 italic dark:text-slate-400">"{data.relationship_summary}"</p>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <CheckSquare className="h-3.5 w-3.5 text-rose-500" />
                        Task Checklist
                    </div>
                    <div className="space-y-1">
                        {loops.map((loop) => (
                            <div
                                key={`${loop.id}-${loop.title}`}
                                className={cn(
                                    'group flex cursor-pointer items-start gap-2 rounded-lg p-2 transition-colors',
                                    loop.isCompleted ? 'bg-emerald-500/5 opacity-60' : 'hover:bg-slate-100 dark:hover:bg-slate-800/50',
                                )}
                                onClick={() => {
                                    if (loop.id > 0) {
                                        void toggleTask(loop.id, loop.isCompleted);
                                        return;
                                    }

                                    toggleLoop(loop.title);
                                }}
                            >
                                {loop.isCompleted ? (
                                    <CheckSquare className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                ) : (
                                    <Square className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-300 group-hover:text-rose-400 dark:text-slate-700" />
                                )}
                                <span className={cn('text-[11px] leading-tight', loop.isCompleted && 'text-muted-foreground line-through')}>
                                    {loop.title}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {data.last_commitment && (
                    <div className="mt-2 flex items-start gap-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                        <Zap className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
                        <div className="space-y-0.5">
                            <span className="text-[10px] font-bold tracking-tight text-amber-600 uppercase">Last Commitment</span>
                            <p className="text-muted-foreground text-[11px] leading-tight">{data.last_commitment}</p>
                        </div>
                    </div>
                )}
            </CardContent>
            {/*wah */}
        </Card>
    );
}
