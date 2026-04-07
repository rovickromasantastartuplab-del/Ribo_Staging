import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Shield, Zap, UserCheck, Settings2, Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

type AutomationMode = 'manual' | 'semi' | 'auto';

interface ModeConfig {
    id: AutomationMode;
    label: string;
    description: string;
    icon: any;
    color: string;
    bgColor: string;
    borderColor: string;
}

const MODES: ModeConfig[] = [
    {
        id: 'manual',
        label: "Manual Protocol",
        description: "Human-in-the-Loop. AI generates suggestions but requires explicit agent approval for every action.",
        icon: UserCheck,
        color: "text-slate-600",
        bgColor: "bg-slate-50",
        borderColor: "border-slate-200"
    },
    {
        id: 'semi',
        label: "Supervised Autopilot",
        description: "Assisted Resolution. AI applies safe classification and linking automatically, but drafts require verification.",
        icon: Settings2,
        color: "text-indigo-600",
        bgColor: "bg-indigo-50/50",
        borderColor: "border-indigo-100"
    },
    {
        id: 'auto',
        label: "Autonomous Operation",
        description: "Direct Resolution. AI resolves qualified low-risk inquiries and archives spam without human intervention.",
        icon: Zap,
        color: "text-orange-600",
        bgColor: "bg-orange-50/50",
        borderColor: "border-orange-100"
    }
];

export default function AiAutomationCard() {
    const [activeMode, setActiveMode] = useState<AutomationMode>('manual');

    return (
        <Card className="border-none shadow-none bg-transparent overflow-hidden">
            <CardHeader className="px-0 pb-4 pt-4">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-xs font-bold uppercase tracking-widest text-slate-500 flex items-center gap-2">
                            <Shield className="w-3.5 h-3.5" />
                            Automation Governance
                        </CardTitle>
                        <CardDescription className="text-[10px] text-slate-400 font-medium">
                            Operational control protocols for AI response agents
                        </CardDescription>
                    </div>
                    <Badge variant="outline" className="text-[9px] font-bold bg-white/50 border-slate-200">
                        {MODES.find(m => m.id === activeMode)?.label}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="px-0 space-y-3">
                <div className="grid grid-cols-1 gap-2">
                    {MODES.map((mode) => {
                        const Icon = mode.icon;
                        const isActive = activeMode === mode.id;
                        
                        return (
                            <button
                                key={mode.id}
                                onClick={() => setActiveMode(mode.id)}
                                className={cn(
                                    "flex items-start gap-3 p-3 rounded-xl border text-left transition-all duration-200 group",
                                    isActive 
                                        ? cn(mode.bgColor, mode.borderColor, "ring-1", mode.borderColor.replace('border-', 'ring-'))
                                        : "bg-white/40 border-slate-100 hover:border-slate-200 hover:bg-white/60"
                                )}
                            >
                                <div className={cn(
                                    "mt-0.5 p-1.5 rounded-lg shrink-0 transition-colors",
                                    isActive ? mode.bgColor : "bg-slate-100 text-slate-400 group-hover:bg-slate-200"
                                )}>
                                    <Icon className={cn("w-4 h-4", isActive ? mode.color : "text-slate-400")} />
                                </div>
                                <div className="space-y-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <h4 className={cn(
                                            "text-xs font-bold transition-colors",
                                            isActive ? "text-slate-900" : "text-slate-500 group-hover:text-slate-700"
                                        )}>
                                            {mode.label}
                                        </h4>
                                        {isActive && (
                                            <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                        )}
                                    </div>
                                    <p className={cn(
                                        "text-[10px] leading-relaxed transition-colors",
                                        isActive ? "text-slate-600 font-medium" : "text-slate-400 group-hover:text-slate-500"
                                    )}>
                                        {mode.description}
                                    </p>
                                </div>
                            </button>
                        );
                    })}
                </div>

                <div className="p-3 rounded-xl bg-amber-500/5 border border-amber-500/10 flex items-start gap-2.5">
                    <Info className="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" />
                    <p className="text-[10px] text-amber-700/80 leading-snug">
                        <strong>Security Notice</strong>: Access to modify autonomous resolution protocols is restricted to system administrators only.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
