import { useState } from 'react';
import { AiDraft, AiTriageResult, getMockDraft } from '../utils/mockAiData';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Sparkles, Copy, Check, MessageSquare, Wand2, Lightbulb, Zap } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { toast } from 'sonner';

interface AiReplyAssistantCardProps {
    triageData: AiTriageResult;
    onInsertDraft: (content: string) => void;
}

export default function AiReplyAssistantCard({ triageData, onInsertDraft }: AiReplyAssistantCardProps) {
    const [tone, setTone] = useState<string>('professional');
    const [draft, setDraft] = useState<AiDraft | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [copied, setCopied] = useState(false);

    const generateDraft = () => {
        setIsGenerating(true);
        setDraft(null);
        
        // Simulate AI thinking
        setTimeout(() => {
            const fetchedDraft = getMockDraft(tone);
            setDraft(fetchedDraft);
            setIsGenerating(false);
            toast.success("Strategic draft generated!");
        }, 1200);
    };

    const copyToClipboard = () => {
        if (!draft) return;
        navigator.clipboard.writeText(draft.body.replace(/<[^>]*>/g, ''));
        setCopied(true);
        toast.success("Draft copied to clipboard");
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Card className="border-none shadow-sm bg-gradient-to-br from-white to-slate-50/50 dark:from-slate-950 dark:to-slate-900/50">
            <CardHeader className="pb-3 flex flex-row items-center justify-between space-y-0">
                <div className="flex items-center gap-2">
                    <div className="p-2 bg-amber-500/10 rounded-lg">
                        <Sparkles className="w-4 h-4 text-amber-500" />
                    </div>
                    <div>
                        <CardTitle className="text-sm font-semibold">Reply Assistant</CardTitle>
                        <p className="text-[10px] text-muted-foreground uppercase tracking-wider font-medium">Magic Draft • Phase 4</p>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Phase 4 Strategic Header */}
                <div className="p-3 rounded-xl bg-amber-500/5 border border-amber-500/10 space-y-2">
                    <div className="flex items-center gap-2 text-amber-600">
                        <Lightbulb className="w-3.5 h-3.5" />
                        <span className="text-[10px] font-bold uppercase tracking-tight">AI Strategy Insight</span>
                    </div>
                    <p className="text-[11px] font-medium leading-tight text-slate-700 dark:text-slate-300">
                        Goal: <span className="text-amber-700 dark:text-amber-500 font-bold">{triageData.strategic_action.goal}</span>. {triageData.strategic_action.recommendation}
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <div className="flex-1">
                        <Select value={tone} onValueChange={setTone}>
                            <SelectTrigger className="h-8 text-xs bg-white dark:bg-slate-900">
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
                        className="h-8 px-3 bg-amber-500 hover:bg-amber-600 text-white"
                        onClick={generateDraft}
                        disabled={isGenerating}
                    >
                        {isGenerating ? (
                            <Wand2 className="w-3.5 h-3.5 animate-spin" />
                        ) : (
                            <>
                                <Wand2 className="w-3.5 h-3.5 mr-1.5" />
                                Generate
                            </>
                        )}
                    </Button>
                </div>

                {draft && (
                    <div className="space-y-3 animate-in fade-in slide-in-from-top-2 duration-300">
                        <div className="p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 relative group">
                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button size="icon" variant="ghost" className="h-7 w-7" onClick={copyToClipboard}>
                                    {copied ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                                </Button>
                            </div>
                            <div 
                                className="text-[11px] leading-relaxed text-slate-700 dark:text-slate-300 prose prose-sm dark:prose-invert max-w-none"
                                dangerouslySetInnerHTML={{ __html: draft.body }}
                            />
                        </div>
                        <Button 
                            className="w-full h-9 gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                            onClick={() => {
                                onInsertDraft(draft.body);
                                toast.success("Draft inserted into composer");
                            }}
                        >
                            <Zap className="w-4 h-4 fill-current" />
                            Insert into Composer
                        </Button>
                    </div>
                )}

                {!draft && !isGenerating && (
                    <div className="py-8 flex flex-col items-center justify-center text-center space-y-2 border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-xl">
                        <MessageSquare className="w-8 h-8 text-slate-200 dark:text-slate-800" />
                        <p className="text-[10px] text-muted-foreground max-w-[150px]">
                            Select a tone and click generate to see the strategic draft.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
