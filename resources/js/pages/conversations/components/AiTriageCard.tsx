import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { BrainCircuit, Info, CheckCircle2, TrendingUp, Target, Zap, MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { toast } from 'sonner';
import { AiTriageResult } from '../utils/mockAiData';

interface AiTriageCardProps {
    data: AiTriageResult;
}

export default function AiTriageCard({ data }: AiTriageCardProps) {
    const getIntentColor = (intent: string) => {
        switch (intent.toLowerCase()) {
            case 'sales': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
            case 'support': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
            case 'billing': return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
            default: return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
        }
    };

    const getPriorityColor = (priority: string) => {
        switch (priority.toLowerCase()) {
            case 'high':
            case 'urgent': return 'bg-rose-500/10 text-rose-500 border-rose-500/20';
            case 'medium': return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
            default: return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
        }
    };

    return (
        <Card className="border-none shadow-sm bg-gradient-to-br from-white to-slate-50/50 dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="pb-3 flex flex-row items-center justify-between space-y-0">
                <div className="flex items-center gap-2">
                    <div className="p-2 bg-indigo-500/10 rounded-lg">
                        <BrainCircuit className="w-4 h-4 text-indigo-500" />
                    </div>
                    <div>
                        <CardTitle className="text-sm font-semibold">Inbox Triage</CardTitle>
                        <p className="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Phase 4 Intelligence</p>
                    </div>
                </div>
                {data.success_probability && (
                    <Badge variant="outline" className="bg-indigo-500/5 text-indigo-500 border-indigo-500/20 gap-1 px-2 py-0.5">
                        <Target className="w-3 h-3" />
                        {data.success_probability}% Win Prob.
                    </Badge>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Phase 4 Strategic Move */}
                {data.strategic_action && (
                    <div className="p-3 rounded-xl bg-indigo-500/5 border border-indigo-500/10 space-y-2">
                        <div className="flex items-center gap-2 text-indigo-500">
                            <Zap className="w-3.5 h-3.5 fill-current" />
                            <span className="text-xs font-bold uppercase tracking-tight">AI Strategic Move</span>
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs font-semibold text-slate-900 dark:text-slate-100 italic">
                                "{data.strategic_action.recommendation}"
                            </p>
                            <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                <Info className="w-3 h-3" />
                                <span>Goal: {data.strategic_action.goal} • {data.strategic_action.reason}</span>
                            </div>
                        </div>
                        <Button 
                            variant="secondary" 
                            size="sm" 
                            className="w-full mt-2 h-7 text-[10px] bg-white dark:bg-slate-900 shadow-sm border-slate-200 dark:border-slate-800 hover:bg-slate-50"
                            onClick={() => toast.success(`Strategic Move Accepted: ${data.strategic_action.goal}`)}
                        >
                            Accept Recommendation
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className={getIntentColor(data.intent)}>
                        {data.intent.toUpperCase()}
                    </Badge>
                    <Badge variant="outline" className={getPriorityColor(data.priority)}>
                        {data.priority.toUpperCase()} PRIORITY
                    </Badge>
                    <Badge variant="outline" className="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-none">
                        {data.intent_confidence}% confidence
                    </Badge>
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <TrendingUp className="w-3.5 h-3.5 text-indigo-500" />
                        AI Summary
                    </div>
                    <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                        {data.summary}
                    </p>
                </div>

                <div className="pt-2 flex items-center justify-between gap-2">
                    <Button 
                        size="sm" 
                        variant="outline" 
                        className="flex-1 h-8 text-[11px] font-medium border-emerald-500/20 hover:bg-emerald-500/5 hover:text-emerald-600 text-emerald-500"
                        onClick={() => toast.success(`Status updated to ${data.suggested_status}`)}
                    >
                        <CheckCircle2 className="w-3.5 h-3.5 mr-1.5" />
                        Set to {data.suggested_status}
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0 border border-slate-200 dark:border-slate-800">
                                <MoreHorizontal className="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel className="text-[10px] font-bold uppercase text-muted-foreground">Triage Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-xs" onClick={() => toast.success("Priority adjusted")}>
                                Apply Recommended Priority
                            </DropdownMenuItem>
                            <DropdownMenuItem className="text-xs" onClick={() => toast.success("Intent labels updated")}>
                                Apply Intent Labels
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-xs text-rose-500" onClick={() => toast.info("Feedback submitted")}>
                                Report Misclassification
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardContent>
        </Card>
    );
}
