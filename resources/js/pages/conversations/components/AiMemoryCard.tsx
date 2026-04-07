import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AiMemorySummary } from '../utils/mockAiData';
import { History, Heart, AlertCircle, Info, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AiMemoryCardProps {
    data: AiMemorySummary;
}

export const AiMemoryCard: React.FC<AiMemoryCardProps> = ({ data }) => {
    const [completedLoops, setCompletedLoops] = React.useState<number[]>([]);

    const toggleLoop = (index: number) => {
        setCompletedLoops(prev => 
            prev.includes(index) ? prev.filter(i => i !== index) : [...prev, index]
        );
    };

    const getSentimentIcon = (sentiment: string) => {
        switch (sentiment) {
            case 'positive': return <Heart className="h-3 w-3 text-emerald-500 fill-emerald-500" />;
            case 'frustrated': return <AlertCircle className="h-3 w-3 text-rose-500" />;
            default: return <Info className="h-3 w-3 text-slate-400" />;
        }
    };

    const getSentimentText = (sentiment: string) => {
        switch (sentiment) {
            case 'positive': return 'Warm relationship';
            case 'frustrated': return 'At risk / Frustrated';
            default: return 'Neutral / Calm';
        }
    };

    return (
        <Card className="border-border shadow-sm mb-4">
            <CardHeader className="pb-2 py-3 border-b border-border/10">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-bold flex items-center gap-2">
                        <History className="h-4 w-4 text-muted-foreground mr-1" />
                        Conversation Memory
                    </CardTitle>
                    <div className="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-muted/30 border border-border/10">
                        {getSentimentIcon(data.sentiment)}
                        <span className="text-[10px] lowercase text-muted-foreground">{getSentimentText(data.sentiment)}</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-4 space-y-4">
                <div className="space-y-1.5">
                    <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider">Relationship Context</span>
                    <p className="text-xs leading-relaxed text-foreground/80">
                        {data.relationship_summary}
                    </p>
                </div>

                {data.open_loops.length > 0 && (
                    <div className="space-y-2">
                        <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider">Open Loops</span>
                        <div className="space-y-1.5">
                            {data.open_loops.map((loop, i) => {
                                const isCompleted = completedLoops.includes(i);
                                return (
                                    <button 
                                        key={i} 
                                        onClick={() => toggleLoop(i)}
                                        className={cn(
                                            "w-full flex items-start gap-2 p-2 rounded-md border border-border/10 italic transition-all text-left group",
                                            isCompleted ? "bg-emerald-50/20 border-emerald-500/20" : "bg-muted/10"
                                        )}
                                    >
                                        <div className={cn(
                                            "h-3 w-3 rounded-sm border mt-0.5 flex items-center justify-center shrink-0 transition-colors",
                                            isCompleted ? "bg-emerald-500 border-emerald-500" : "border-muted-foreground/30 group-hover:border-primary/50"
                                        )}>
                                            {isCompleted && <CheckCircle2 className="h-2 w-2 text-white" />}
                                        </div>
                                        <span className={cn(
                                            "text-xs transition-all",
                                            isCompleted ? "text-emerald-700/60 line-through" : "text-foreground/70"
                                        )}>
                                            {loop}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="pt-2 border-t border-border/20">
                    <div className="flex items-center justify-between text-[10px] uppercase font-medium">
                        <span className="text-muted-foreground">Last Commitment</span>
                    </div>
                    <p className="text-xs text-foreground/70 mt-1">
                        {data.last_commitment}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
};
