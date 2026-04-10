import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';
import { Check, Copy, Lightbulb, MessageSquare, Sparkles, Wand2, Zap } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AiDraft, AiTriageResult, getAllowedActions } from '../utils/mockAiData';
interface AiReplyAssistantCardProps {
    threadId?: number | null;
    triageData: AiTriageResult;
    onInsertDraft: (content: string) => void;
}

export default function AiReplyAssistantCard({ threadId, triageData, onInsertDraft }: AiReplyAssistantCardProps) {
    const [tone, setTone] = useState<string>('professional');
    const [draft, setDraft] = useState<AiDraft | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [copied, setCopied] = useState(false);
    const canGenerateReply = getAllowedActions(triageData).some((action) => action.toLowerCase().includes('reply'));

    const generateDraft = async () => {
        if (!canGenerateReply) {
            toast.warning('Reply generation is blocked for the current triage state.');
            return;
        }

        const resolvedThreadId = threadId ?? triageData.email_thread_id;
        if (!resolvedThreadId) {
            toast.warning('No thread context available for draft generation.');
            return;
        }

        setIsGenerating(true);
        setDraft(null);

        try {
            const prompt = `Create a ${tone} reply. Goal: ${triageData.strategic_action.goal}. Context: ${triageData.summary}`;
            const response = await axios.post('/ai/draft', {
                threadId: resolvedThreadId,
                prompt,
                tone,
            });

            setDraft({
                subject: String(response?.data?.data?.subject ?? ''),
                body: String(response?.data?.data?.body ?? ''),
            });
            toast.success('Strategic draft generated!');
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                toast.warning('AI is currently unavailable.');
            } else {
                toast.error('Failed to generate AI draft.');
            }
        } finally {
            setIsGenerating(false);
        }
    };

    const copyToClipboard = () => {
        if (!draft) return;
        navigator.clipboard.writeText(draft.body.replace(/<[^>]*>/g, ''));
        setCopied(true);
        toast.success('Draft copied to clipboard');
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Card className="border-none bg-gradient-to-br from-white to-slate-50/50 shadow-sm dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <div className="flex items-center gap-2">
                    <div className="rounded-lg bg-amber-500/10 p-2">
                        <Sparkles className="h-4 w-4 text-amber-500" />
                    </div>
                    <div>
                        <CardTitle className="text-sm font-semibold">Smart Reply Assistant</CardTitle>
                        <p className="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Automatic Compose</p>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Phase 4 Strategic Header */}
                <div className="space-y-2 rounded-xl border border-amber-500/10 bg-amber-500/5 p-3">
                    <div className="flex items-center gap-2 text-amber-600">
                        <Lightbulb className="h-3.5 w-3.5" />
                        <span className="text-[10px] font-bold tracking-tight uppercase">AI Suggestion</span>
                    </div>
                    <p className="text-[11px] leading-tight font-medium text-slate-700 dark:text-slate-300">
                        Goal: <span className="font-bold text-amber-700 dark:text-amber-500">{triageData.strategic_action.goal}</span>.{' '}
                        {triageData.strategic_action.recommendation}
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <div className="flex-1">
                        <Select value={tone} onValueChange={setTone}>
                            <SelectTrigger className="h-8 bg-white text-xs dark:bg-slate-900">
                                <SelectValue placeholder="Select tone" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="professional">Professional</SelectItem>
                                <SelectItem value="friendly">Friendly</SelectItem>
                                <SelectItem value="concise">Concise</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        size="sm"
                        className="h-8 bg-amber-500 px-3 text-white hover:bg-amber-600"
                        onClick={generateDraft}
                        disabled={isGenerating || !canGenerateReply}
                    >
                        {isGenerating ? (
                            <Wand2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                            <>
                                <Wand2 className="mr-1.5 h-3.5 w-3.5" />
                                Generate
                            </>
                        )}
                    </Button>
                </div>

                {draft && (
                    <div className="animate-in fade-in slide-in-from-top-2 space-y-3 duration-300">
                        <div className="group relative rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                <Button size="icon" variant="ghost" className="h-7 w-7" onClick={copyToClipboard}>
                                    {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                                </Button>
                            </div>
                            <div
                                className="prose prose-sm dark:prose-invert max-w-none text-[11px] leading-relaxed text-slate-700 dark:text-slate-300"
                                dangerouslySetInnerHTML={{ __html: draft.body }}
                            />
                        </div>
                        <Button
                            className="h-9 w-full gap-2 bg-indigo-600 font-medium text-white hover:bg-indigo-700"
                            onClick={() => {
                                onInsertDraft(draft.body);
                                toast.success('Draft inserted into composer');
                            }}
                        >
                            <Zap className="h-4 w-4 fill-current" />
                            Use this reply
                        </Button>
                    </div>
                )}

                {!draft && !isGenerating && (
                    <div className="flex flex-col items-center justify-center space-y-2 rounded-xl border-2 border-dashed border-slate-100 py-8 text-center dark:border-slate-800">
                        <MessageSquare className="h-8 w-8 text-slate-200 dark:text-slate-800" />
                        <p className="text-muted-foreground max-w-[150px] text-[10px]">
                            {canGenerateReply
                                ? 'Select a tone and click generate to see the strategic draft.'
                                : 'Reply drafting is unavailable until triage allows a reply action.'}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
