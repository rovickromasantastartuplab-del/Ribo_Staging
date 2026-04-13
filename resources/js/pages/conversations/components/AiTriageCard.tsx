import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import axios from 'axios';
import { BookOpen, BrainCircuit, CheckCircle2, Download, Layout, MoreHorizontal, RefreshCw, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AiTriageResult, deriveStateInfo } from '../utils/mockAiData';

interface AiTriageCardProps {
    data: AiTriageResult;
}

interface OpportunityOption {
    id: number;
    name: string;
    amount: number;
    stage: string;
}

export default function AiTriageCard({ data }: AiTriageCardProps) {
    const [selectedOppId, setSelectedOppId] = useState<string>('overall');
    const [isExporting, setIsExporting] = useState(false);
    const [opportunityOptions, setOpportunityOptions] = useState<OpportunityOption[]>([]);
    const stateInfo = deriveStateInfo(data.thread_state);

    useEffect(() => {
        if (!data.email_thread_id) {
            return;
        }

        axios
            .get(`/ai/reports/options/${data.email_thread_id}`)
            .then((response) => {
                const options = response?.data?.data?.opportunities;
                setOpportunityOptions(Array.isArray(options) ? options : []);
            })
            .catch(() => {
                setOpportunityOptions([]);
            });
    }, [data.email_thread_id]);

    const getIntentColor = (intent: string) => {
        switch (intent.toLowerCase()) {
            case 'sales':
                return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
            case 'support':
                return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
            case 'billing':
                return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
            default:
                return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
        }
    };

    const getPriorityColor = (priority: string) => {
        switch (priority.toLowerCase()) {
            case 'high':
            case 'urgent':
                return 'bg-rose-500/10 text-rose-500 border-rose-500/20';
            case 'medium':
                return 'bg-orange-500/10 text-orange-500 border-orange-500/20';
            default:
                return 'bg-slate-500/10 text-slate-500 border-slate-500/20';
        }
    };

    return (
        <Card className="border-none bg-gradient-to-br from-white to-slate-50/50 shadow-sm dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <div className="flex items-center gap-2">
                    <div className="rounded-lg bg-indigo-500/10 p-2">
                        <BrainCircuit className="h-4 w-4 text-indigo-500" />
                    </div>
                    <div>
                        <CardTitle className="text-sm font-semibold">AI Assistant Summary</CardTitle>
                        <p className="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Quick Glance</p>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className={getIntentColor(data.intent)}>
                        {data.intent.toUpperCase()}
                    </Badge>
                    <Badge variant="outline" className={getPriorityColor(data.priority)}>
                        {data.priority.toUpperCase()} PRIORITY
                    </Badge>
                    <Badge variant="outline" className="border-none bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        {data.intent_confidence}% confidence
                    </Badge>
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <TrendingUp className="h-3.5 w-3.5 text-indigo-500" />
                        AI Summary
                    </div>
                    <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-400">{data.summary}</p>
                </div>

                <div className="flex items-center justify-between gap-2 pt-2">
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-8 flex-1 border-emerald-500/20 text-[11px] font-medium text-emerald-500 hover:bg-emerald-500/5 hover:text-emerald-600"
                        onClick={() => toast.success(`State updated to ${stateInfo.label}`)}
                    >
                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                        Set to {stateInfo.label}
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="h-8 w-8 border border-slate-200 p-0 dark:border-slate-800">
                                <MoreHorizontal className="text-muted-foreground h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel className="text-muted-foreground text-[10px] font-bold uppercase">Triage Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-xs" onClick={() => toast.success('Priority adjusted')}>
                                Apply Recommended Priority
                            </DropdownMenuItem>
                            <DropdownMenuItem className="text-xs" onClick={() => toast.success('Intent labels updated')}>
                                Apply Intent Labels
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-xs text-rose-500" onClick={() => toast.info('Feedback submitted')}>
                                Report Misclassification
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <Separator className="my-2 opacity-50" />
                <div className="space-y-3 pt-2">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-indigo-500 uppercase">
                            <Layout className="h-3 w-3" />
                            AI Summary Reports
                        </div>
                    </div>

                    <div className="space-y-2.5">
                        <div className="space-y-1">
                            <label className="text-muted-foreground ml-1 text-[9px] font-bold uppercase">Select Report Scope</label>
                            <Select value={selectedOppId} onValueChange={setSelectedOppId}>
                                <SelectTrigger className="h-8 border-slate-200 bg-slate-50/50 text-[11px] focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900/50">
                                    <SelectValue placeholder="Select context" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="overall" className="text-xs font-semibold italic">
                                        Full History (Leads & All Opportunities)
                                    </SelectItem>
                                    <SelectItem value="lead-only" className="text-xs">
                                        Leads History Only
                                    </SelectItem>
                                    <SelectItem value="all-opps" className="text-xs">
                                        All Opportunities Summary
                                    </SelectItem>
                                    {opportunityOptions.map((opportunity) => (
                                        <SelectItem key={opportunity.id} value={`opportunity:${opportunity.id}`} className="text-xs">
                                            {opportunity.name} ({opportunity.stage})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            variant="default"
                            size="sm"
                            className="group relative h-10 w-full gap-2.5 overflow-hidden bg-indigo-600 text-xs font-bold text-white shadow-lg shadow-indigo-100 transition-all hover:scale-[1.01] hover:bg-indigo-700 dark:shadow-none"
                            onClick={async () => {
                                if (!data.email_thread_id) {
                                    toast.warning('No thread context available for report generation.');
                                    return;
                                }

                                setIsExporting(true);
                                const toastId = toast.loading('AI is generating your summary report...');

                                const isSpecificOpportunity = selectedOppId.startsWith('opportunity:');
                                const opportunityId = isSpecificOpportunity ? Number(selectedOppId.split(':')[1]) : null;
                                const scope = isSpecificOpportunity
                                    ? 'specific-opportunity'
                                    : selectedOppId === 'lead-only'
                                      ? 'leads-only'
                                      : selectedOppId === 'all-opps'
                                        ? 'all-opps'
                                        : 'overall';

                                try {
                                    const response = await axios.post('/ai/reports/generate', {
                                        threadId: data.email_thread_id,
                                        scope,
                                        contactId: null,
                                        opportunityId,
                                    });

                                    const jobId = response?.data?.data?.job_id;
                                    if (!jobId) {
                                        toast.dismiss(toastId);
                                        toast.warning('Report generated but job id was unavailable.');
                                        return;
                                    }

                                    const downloadResponse = await axios.get(`/ai/reports/${jobId}/download`, {
                                        responseType: 'blob',
                                    });

                                    const blob = new Blob([downloadResponse.data], { type: 'application/pdf' });
                                    const url = window.URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.style.display = 'none';
                                    a.href = url;
                                    a.download = `AI-Summary-Report-${data.email_thread_id}.pdf`;
                                    document.body.appendChild(a);
                                    a.click();
                                    window.URL.revokeObjectURL(url);
                                    document.body.removeChild(a);

                                    toast.dismiss(toastId);
                                    toast.success('Summary PDF generated and downloaded!');
                                } catch (error: unknown) {
                                    toast.dismiss(toastId);
                                    if (axios.isAxiosError(error)) {
                                        const status = error.response?.status;
                                        const code = (error.response?.data as { code?: string } | undefined)?.code;

                                        if (status === 409 || code === 'report_result_unavailable') {
                                            toast.warning('Report generated but no downloadable result is available yet.');
                                            return;
                                        }

                                        if (status === 422) {
                                            toast.warning('AI is currently unavailable.');
                                            return;
                                        }
                                    }

                                    toast.error('Failed to generate summary report.');
                                } finally {
                                    setIsExporting(false);
                                }
                            }}
                            disabled={isExporting}
                        >
                            {isExporting ? (
                                <RefreshCw className="h-4 w-4 animate-spin text-white/50" />
                            ) : (
                                <>
                                    <BookOpen className="h-4 w-4 text-indigo-200 transition-transform group-hover:scale-110" />
                                    Download Summary Report
                                    <Download className="ml-auto h-3.5 w-3.5 opacity-50 transition-transform group-hover:translate-y-0.5" />
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
