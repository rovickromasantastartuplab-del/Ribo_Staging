import React, { useState } from 'react';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AiMemorySummary, AiTriageResult } from '../utils/mockAiData';
import { History, Heart, AlertCircle, CheckSquare, Square, Zap, Activity } from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

interface AiMemoryCardProps {
    data: AiMemorySummary;
    triageData?: AiTriageResult;
}

export default function AiMemoryCard({ data, triageData }: AiMemoryCardProps) {
    const [completedLoops, setCompletedLoops] = useState<string[]>([]);
    const [taskCompletionOverrides, setTaskCompletionOverrides] = useState<Record<number, boolean>>({});
    const [updatingTaskIds, setUpdatingTaskIds] = useState<number[]>([]);

    const toggleLoop = (loop: string) => {
        setCompletedLoops(prev => 
            prev.includes(loop) 
                ? prev.filter(l => l !== loop) 
                : [...prev, loop]
        );
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

    const pulse = triageData?.behavioral_pulse || 'stable';
    const loops = data.tasks?.length
        ? data.tasks.map((task) => ({
              id: task.id,
              title: task.title,
              isCompleted:
                  task.id in taskCompletionOverrides
                      ? taskCompletionOverrides[task.id]
                      : Boolean(task.is_completed),
          }))
        : data.open_loops.map((loop, idx) => ({
              id: -(idx + 1),
              title: loop,
              isCompleted: completedLoops.includes(loop),
          }));

    return (
        <Card className="border-none shadow-sm bg-gradient-to-br from-white to-slate-50/50 dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="pb-3 flex flex-row items-center justify-between space-y-0 text-sm font-medium">
                <div className="flex items-center gap-2">
                    <div className="p-2 bg-rose-500/10 rounded-lg">
                        <History className="w-4 h-4 text-rose-500" />
                    </div>
                    <CardTitle className="text-sm font-semibold text-slate-900 dark:text-slate-100">Relationship History</CardTitle>
                </div>
                <Badge variant="outline" className={cn(
                    "gap-1 px-2 py-0.5 border-none",
                    data.sentiment === 'positive' ? "bg-emerald-500/10 text-emerald-600" : 
                    data.sentiment === 'frustrated' ? "bg-rose-500/10 text-rose-600" : 
                    "bg-slate-500/10 text-slate-600"
                )}>
                    {data.sentiment === 'positive' ? <Heart className="w-3 h-3 fill-current" /> : <AlertCircle className="w-3 h-3" />}
                    {data.sentiment.toUpperCase()} MOOD
                </Badge>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                {/* Phase 4 Behavioral Pulse */}
                <div className="p-3 rounded-xl bg-slate-500/5 border border-slate-500/10 space-y-2">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2 text-slate-500">
                            <Activity className="w-3.5 h-3.5" />
                            <span className="text-[10px] font-bold uppercase tracking-tight">Response Speed</span>
                        </div>
                        {pulse === 'heating_up' && (
                            <Badge className="bg-orange-500 text-white text-[9px] h-4 border-none animate-pulse">
                                🔥 HEATING UP
                            </Badge>
                        )}
                    </div>
                    <div className="flex items-end gap-2">
                        <p className="text-[11px] text-muted-foreground font-medium">
                            Interaction rhythm is <span className="text-slate-900 dark:text-slate-100">{pulse === 'heating_up' ? '+22% faster' : 'stable'}</span> compared to last week.
                        </p>
                    </div>
                </div>

                <div className="space-y-1.5">
                    <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed font-medium italic">
                        "{data.relationship_summary}"
                    </p>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <CheckSquare className="w-3.5 h-3.5 text-rose-500" />
                        Task Checklist
                    </div>
                    <div className="space-y-1">
                        {loops.map((loop) => (
                            <div 
                                key={`${loop.id}-${loop.title}`} 
                                className={cn(
                                    "flex items-start gap-2 p-2 rounded-lg transition-colors cursor-pointer group",
                                    loop.isCompleted ? "bg-emerald-500/5 opacity-60" : "hover:bg-slate-100 dark:hover:bg-slate-800/50"
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
                                    <CheckSquare className="w-3.5 h-3.5 text-emerald-500 mt-0.5 shrink-0" />
                                ) : (
                                    <Square className="w-3.5 h-3.5 text-slate-300 dark:text-slate-700 mt-0.5 shrink-0 group-hover:text-rose-400" />
                                )}
                                <span className={cn(
                                    "text-[11px] leading-tight",
                                    loop.isCompleted && "line-through text-muted-foreground"
                                )}>
                                    {loop.title}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {data.last_commitment && (
                    <div className="pt-2 flex items-start gap-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                        <Zap className="w-3.5 h-3.5 text-amber-500 mt-0.5 shrink-0" />
                        <div className="space-y-0.5">
                            <span className="text-[10px] font-bold text-amber-600 uppercase tracking-tight">Last Commitment</span>
                            <p className="text-[11px] text-muted-foreground leading-tight">
                                {data.last_commitment}
                            </p>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
