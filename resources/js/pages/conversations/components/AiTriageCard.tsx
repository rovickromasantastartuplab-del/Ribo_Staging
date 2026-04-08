import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    BrainCircuit, 
    Info, 
    CheckCircle2, 
    TrendingUp, 
    Target, 
    Zap, 
    MoreHorizontal, 
    Sparkles,
    FileText,
    BookOpen,
    Download,
    ChevronDown,
    Layout,
    RefreshCw
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { toast } from 'sonner';
import { AiTriageResult, getMockOpportunities } from '../utils/mockAiData';

interface AiTriageCardProps {
    data: AiTriageResult;
}

export default function AiTriageCard({ data }: AiTriageCardProps) {
    const [selectedOppId, setSelectedOppId] = useState<string>("full-history");
    const [isExporting, setIsExporting] = useState(false);
    
    // Mock data for prototype
    const opportunities = getMockOpportunities(0); 
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
                        <CardTitle className="text-sm font-semibold">AI Assistant Summary</CardTitle>
                        <p className="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Quick Glance</p>
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
                            <span className="text-xs font-bold uppercase tracking-tight">AI Suggested Action</span>
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

                <Separator className="my-2 opacity-50" />

                {/* New: AI Summary Reports Section */}
                <div className="space-y-3 pt-2">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-indigo-500">
                            <Layout className="w-3 h-3" />
                            AI Summary Reports
                        </div>
                        <Badge variant="outline" className="text-[9px] font-black bg-indigo-500/10 text-indigo-600 border-none px-1.5 py-0">PROTOTYPE</Badge>
                    </div>

                    <div className="space-y-2.5">
                        <div className="space-y-1">
                            <label className="text-[9px] font-bold text-muted-foreground uppercase ml-1">Select Report Scope</label>
                            <Select value={selectedOppId} onValueChange={setSelectedOppId}>
                                <SelectTrigger className="h-8 text-[11px] bg-slate-50/50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-800 focus:ring-indigo-500">
                                    <SelectValue placeholder="Select context" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="full-history" className="text-xs font-semibold italic">Full Activity History</SelectItem>
                                    <DropdownMenuSeparator />
                                    {opportunities.map(opp => (
                                        <SelectItem key={opp.id} value={opp.id.toString()} className="text-xs">
                                            {opp.name} (${(opp.value/1000).toFixed(1)}k)
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button 
                            variant="default"
                            size="sm"
                            className="w-full h-10 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs gap-2.5 shadow-lg shadow-indigo-100 dark:shadow-none transition-all hover:scale-[1.01] overflow-hidden group relative"
                            onClick={() => {
                                setIsExporting(true);
                                const toastId = toast.loading("AI is generating your summary report...");
                                setTimeout(() => {
                                    toast.dismiss(toastId);
                                    toast.success("Summary Report (PDF) generated and downloaded.");
                                    setIsExporting(false);
                                }, 2500);
                            }}
                            disabled={isExporting}
                        >
                            {isExporting ? (
                                <RefreshCw className="w-4 h-4 animate-spin text-white/50" />
                            ) : (
                                <>
                                    <BookOpen className="w-4 h-4 text-indigo-200 group-hover:scale-110 transition-transform" />
                                    Download Summary Report (PDF)
                                    <Download className="w-3.5 h-3.5 ml-auto opacity-50 group-hover:translate-y-0.5 transition-transform" />
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
