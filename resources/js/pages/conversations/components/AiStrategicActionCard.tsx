import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Zap, Target, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';

interface AiStrategicActionCardProps {
    data: {
        goal: string;
        reason: string;
        recommendation: string;
    } | null;
}

export default function AiStrategicActionCard({ data }: AiStrategicActionCardProps) {
    if (!data) return null;

    return (
        <Card className="border-none shadow-sm bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/20 dark:to-slate-900/50 overflow-hidden ring-1 ring-indigo-500/10">
            <CardHeader className="pb-2 pt-4 px-4 flex flex-row items-center justify-between space-y-0">
                <div className="flex items-center gap-2">
                    <div className="p-1.5 bg-indigo-500/10 rounded-md">
                        <Zap className="w-3.5 h-3.5 text-indigo-500 fill-current" />
                    </div>
                    <CardTitle className="text-[11px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">AI Suggested Action</CardTitle>
                </div>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-1 space-y-3">
                <div className="space-y-1.5">
                    <p className="text-sm font-bold text-slate-900 dark:text-slate-100 italic leading-snug">
                        "{data.recommendation}"
                    </p>
                    <div className="flex items-start gap-1.5 p-2 rounded-lg bg-white/50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800">
                        <Info className="w-3 h-3 text-slate-400 mt-0.5 shrink-0" />
                        <span className="text-[10px] text-slate-500 font-medium">
                            <strong className="text-slate-700 dark:text-slate-300">Goal:</strong> {data.goal} • {data.reason}
                        </span>
                    </div>
                </div>
                
                <Button 
                    variant="default" 
                    size="sm" 
                    className="w-full h-8 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] gap-2 shadow-md shadow-indigo-100 dark:shadow-none transition-all active:scale-95"
                    onClick={() => toast.success(`Strategic Action: ${data.goal} initiated.`)}
                >
                    Apply Strategy
                </Button>
            </CardContent>
        </Card>
    );
}
