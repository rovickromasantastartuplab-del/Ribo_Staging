import React from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AiTriageResult } from '../utils/mockAiData';
import { Zap, AlertCircle, CheckCircle2, MoreHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';

interface AiTriageCardProps {
    data: AiTriageResult;
    onApply?: (type: 'status' | 'priority' | 'all') => void;
}

export const AiTriageCard: React.FC<AiTriageCardProps> = ({ data, onApply }) => {
    const getIntentColor = (intent: string) => {
        switch (intent) {
            case 'sales': return 'bg-emerald-500/10 text-emerald-600 border-emerald-200';
            case 'support': return 'bg-blue-500/10 text-blue-600 border-blue-200';
            case 'billing': return 'bg-purple-500/10 text-purple-600 border-purple-200';
            case 'spam': return 'bg-rose-500/10 text-rose-600 border-rose-200';
            default: return 'bg-muted text-muted-foreground border-border';
        }
    };

    const getPriorityColor = (priority: string) => {
        switch (priority) {
            case 'high':
            case 'urgent': return 'bg-orange-500 text-white';
            case 'medium': return 'bg-amber-500 text-white';
            default: return 'bg-slate-500 text-white';
        }
    };

    return (
        <Card className="border-primary/20 shadow-sm overflow-hidden mb-4">
            <CardHeader className="pb-2 bg-primary/5 py-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-bold flex items-center gap-2">
                        <Zap className="h-4 w-4 text-primary" />
                        AI Inbox Triage
                    </CardTitle>
                    <Badge variant="outline" className={cn("text-[10px] px-1.5 py-0", getIntentColor(data.intent))}>
                        {data.intent.toUpperCase()}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="pt-4 space-y-4">
                <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-xs text-muted-foreground font-medium uppercase tracking-wider">
                        <span>Analysis Summary</span>
                        <span className="text-[10px] opacity-70">{data.intent_confidence}% confidence</span>
                    </div>
                    <p className="text-sm leading-relaxed text-foreground/90 italic">
                        "{data.summary}"
                    </p>
                </div>

                <div className="flex flex-wrap gap-2 pt-1">
                    <Badge className={cn("text-[10px] h-5", getPriorityColor(data.priority))}>
                        {data.priority.toUpperCase()} PRIORITY
                    </Badge>
                    {data.low_risk_resolution && (
                        <Badge variant="secondary" className="text-[10px] h-5 bg-blue-50 text-blue-600 border-blue-100">
                            LOW RISK
                        </Badge>
                    )}
                    <Badge variant="outline" className="text-[10px] h-5">
                        SUGGESTED: {data.suggested_status}
                    </Badge>
                </div>
            </CardContent>
            <CardFooter className="pt-0 pb-4 flex gap-2">
                <Button 
                    size="sm" 
                    variant="outline" 
                    className="h-8 text-xs flex-1 border-primary/20 hover:bg-primary/5 hover:text-primary transition-colors"
                    onClick={() => onApply?.('all')}
                >
                    <CheckCircle2 className="h-3.5 w-3.5 mr-1.5" />
                    Apply All
                </Button>
                
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="sm" variant="ghost" className="h-8 w-8 p-0 border border-border">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel className="text-[10px] font-bold uppercase text-muted-foreground">{data.intent} Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-xs" onClick={() => onApply?.('status')}>
                            Apply Status: {data.suggested_status}
                        </DropdownMenuItem>
                        <DropdownMenuItem className="text-xs" onClick={() => onApply?.('priority')}>
                            Apply Priority: {data.priority}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </CardFooter>
        </Card>
    );
};
