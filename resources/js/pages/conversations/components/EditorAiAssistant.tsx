import React, { useState } from 'react';
import axios from 'axios';
import { 
    Popover, 
    PopoverContent, 
    PopoverTrigger 
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { 
    Sparkles, 
    Wand2, 
    Check, 
    RefreshCw,
    CornerDownLeft,
    X,
    ChevronLeft
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

interface EditorAiAssistantProps {
    threadId: number | null;
    onInsertDraft: (content: string) => void;
    disabled?: boolean;
}

export default function EditorAiAssistant({ 
    threadId, 
    onInsertDraft, 
    disabled 
}: EditorAiAssistantProps) {
    const [view, setView] = useState<'input' | 'output'>('input');
    const [prompt, setPrompt] = useState('');
    const [generatedDraft, setGeneratedDraft] = useState<string | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [open, setOpen] = useState(false);
    const [tone, setTone] = useState<'professional' | 'friendly'>('professional');

    const handleGenerate = async () => {
        if (!threadId || !prompt.trim()) return;
        setIsGenerating(true);

        try {
            const response = await axios.post('/ai/draft', {
                threadId,
                prompt,
                tone,
            });

            setGeneratedDraft(String(response?.data?.data?.body ?? ''));
            setView('output');
            toast.success(`Generated ${tone} response`);
        } catch (error: unknown) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const data = error.response?.data as { blocked?: boolean; reason?: string } | undefined;
                if (data?.blocked) {
                    toast.warning(
                        'Draft blocked — this thread is closed. Use a recovery instruction (e.g. "write a win-back email") to send a message.',
                        { duration: 6000 }
                    );
                } else {
                    toast.warning('AI is currently unavailable.');
                }
            } else {
                toast.error('Failed to generate AI draft.');
            }
        } finally {
            setIsGenerating(false);
        }
    };

    const handleInsert = () => {
        if (generatedDraft) {
            onInsertDraft(generatedDraft);
            setOpen(false);
            // Reset for next time
            setPrompt('');
            setGeneratedDraft(null);
            setView('input');
            toast.success("Draft inserted into message");
        }
    };

    const handleBack = () => {
        setView('input');
        setGeneratedDraft(null);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button 
                    variant="ghost" 
                    size="icon" 
                    className={cn(
                        "h-7 w-7 transition-all rounded-full hover:bg-indigo-500/10 hover:text-indigo-600 group",
                        open && "bg-indigo-500/10 text-indigo-600 shadow-inner"
                    )}
                    disabled={disabled || !threadId}
                    title="Write for me (AI Assistant)"
                >
                    <Sparkles className={cn(
                        "h-3.5 w-3.5 transition-transform duration-500",
                        open ? "scale-110 rotate-12" : "group-hover:scale-110"
                    )} />
                </Button>
            </PopoverTrigger>
            <PopoverContent 
                side="top" 
                align="start" 
                className="w-80 p-0 overflow-hidden border border-slate-200 dark:border-slate-800 shadow-2xl rounded-xl bg-white dark:bg-slate-950 animate-in zoom-in-95 duration-200"
                sideOffset={12}
            >
                {/* HubSpot-Style Headers */}
                <div className="px-4 py-3 border-b flex items-center justify-between bg-white dark:bg-slate-950">
                    <div className="flex items-center gap-2">
                        {view === 'output' && (
                            <Button variant="ghost" size="icon" className="h-6 w-6 mr-1" onClick={handleBack}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                        )}
                        <h3 className="text-xs font-bold text-slate-900 dark:text-slate-100 italic">Write for me</h3>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="px-1.5 py-0.5 rounded bg-gradient-to-r from-pink-500 to-violet-600 text-[9px] font-black text-white flex items-center gap-1 shadow-sm">
                            <Sparkles className="w-2.5 h-2.5 fill-current" />
                            AI
                        </div>
                        <Button variant="ghost" size="icon" className="h-6 w-6 text-slate-400" onClick={() => setOpen(false)}>
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div className="p-4">
                    {view === 'input' ? (
                        <div className="space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-300">
                            <div className="relative">
                                <Textarea 
                                    placeholder="Example: Professional follow-up regarding yesterday's call"
                                    value={prompt}
                                    onChange={(e) => setPrompt(e.target.value)}
                                    className="min-h-[100px] text-xs resize-none focus-visible:ring-indigo-500 border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 p-3"
                                    disabled={isGenerating}
                                />
                            </div>

                             <div className="flex items-center justify-between gap-4">
                                <div className="flex items-center bg-slate-100 dark:bg-slate-900 rounded-lg p-0.5 border border-slate-200 dark:border-slate-800">
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className={cn(
                                            "h-7 px-3 text-[10px] font-bold rounded-md transition-all",
                                            tone === 'professional' ? "bg-white dark:bg-slate-800 shadow-sm text-indigo-600" : "text-slate-500"
                                        )}
                                        onClick={() => setTone('professional')}
                                    >
                                        Professional
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className={cn(
                                            "h-7 px-3 text-[10px] font-bold rounded-md transition-all",
                                            tone === 'friendly' ? "bg-white dark:bg-slate-800 shadow-sm text-indigo-600" : "text-slate-500"
                                        )}
                                        onClick={() => setTone('friendly')}
                                    >
                                        Friendly
                                    </Button>
                                </div>

                                <Button 
                                    className="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-100 dark:shadow-none font-bold text-[11px] gap-2 transition-all active:scale-95 disabled:opacity-50 ml-auto"
                                    onClick={handleGenerate}
                                    disabled={isGenerating || !prompt.trim()}
                                >
                                    {isGenerating ? (
                                        <Wand2 className="w-3.5 h-3.5 animate-spin text-white/70" />
                                    ) : (
                                        <RefreshCw className="w-3.5 h-3.5 text-indigo-200" />
                                    )}
                                    Generate
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                            <div className="p-3 rounded-lg bg-indigo-50/30 dark:bg-indigo-900/10 border border-indigo-100/50 dark:border-indigo-500/20">
                                <div 
                                    className="text-[11px] leading-relaxed text-slate-700 dark:text-slate-300 max-h-[160px] overflow-y-auto scrollbar-thin transition-all"
                                    dangerouslySetInnerHTML={{ __html: generatedDraft || '' }}
                                />
                            </div>

                            <div className="flex items-center gap-2 justify-end">
                                <Button 
                                    variant="outline"
                                    className="h-9 px-3 text-[11px] font-bold gap-2"
                                    onClick={handleGenerate}
                                    disabled={isGenerating}
                                >
                                    <RefreshCw className={cn("w-3.5 h-3.5", isGenerating && "animate-spin")} />
                                    Regenerate
                                </Button>
                                <Button 
                                    className="h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold gap-2 shadow-lg shadow-indigo-100 dark:shadow-none transition-all active:scale-95"
                                    onClick={handleInsert}
                                >
                                    Insert
                                    <CornerDownLeft className="w-3.5 h-3.5" />
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="bg-slate-50 dark:bg-slate-900/50 py-2 border-t flex justify-center">
                    <div className="flex items-center gap-1.5 opacity-50">
                        <Check className="w-2.5 h-2.5 text-emerald-500" />
                        <span className="text-[8px] font-black uppercase tracking-widest text-slate-500">Corporate Intelligence Active</span>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
