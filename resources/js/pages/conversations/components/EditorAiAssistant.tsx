import React, { useState } from 'react';
import { 
    Popover, 
    PopoverContent, 
    PopoverTrigger 
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { 
    Sparkles, 
    Wand2, 
    Zap, 
    Cpu, 
    Check, 
    ChevronRight,
    SearchCode,
    MessageSquareQuote
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { getMockDraft, getMockScrapedLead } from '../utils/mockAiData';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";

interface EditorAiAssistantProps {
    threadId: number | null;
    onInsertDraft: (content: string) => void;
    onAiConvert: () => void;
    disabled?: boolean;
}

export default function EditorAiAssistant({ 
    threadId, 
    onInsertDraft, 
    onAiConvert,
    disabled 
}: EditorAiAssistantProps) {
    const [tone, setTone] = useState<'professional' | 'friendly' | 'concise'>('professional');
    const [isGenerating, setIsGenerating] = useState(false);
    const [open, setOpen] = useState(false);

    const handleGenerate = () => {
        if (!threadId) return;
        setIsGenerating(true);
        
        // Simulate AI processing
        setTimeout(() => {
            const draft = getMockDraft(tone);
            onInsertDraft(draft.body);
            setIsGenerating(false);
            setOpen(false);
            toast.success(`AI Draft (${tone}) inserted directly into editor.`);
        }, 1200);
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
                    title="AI Assistant Hub"
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
                className="w-80 p-0 overflow-hidden border-none shadow-2xl rounded-xl bg-white dark:bg-slate-950"
                sideOffset={12}
            >
                <div className="p-4 bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 text-white">
                    <div className="flex items-center gap-2 mb-1">
                        <Cpu className="w-4 h-4 text-indigo-200" />
                        <h3 className="text-sm font-bold tracking-tight">AI Intelligence Hub</h3>
                    </div>
                    <p className="text-[10px] text-indigo-100/80 font-medium uppercase tracking-widest">Select an intelligence action</p>
                </div>

                <Tabs defaultValue="draft" className="w-full">
                    <div className="px-4 pt-3 border-b border-slate-100 dark:border-slate-900">
                        <TabsList className="grid w-full grid-cols-2 h-9 p-1 bg-slate-100/50 dark:bg-slate-900/50 rounded-lg">
                            <TabsTrigger value="draft" className="text-[10px] font-bold uppercase tracking-tight data-[state=active]:bg-white dark:data-[state=active]:bg-slate-800 data-[state=active]:shadow-sm">
                                <MessageSquareQuote className="w-3 h-3 mr-1.5" />
                                Smart Draft
                            </TabsTrigger>
                            <TabsTrigger value="convert" className="text-[10px] font-bold uppercase tracking-tight data-[state=active]:bg-white dark:data-[state=active]:bg-slate-800 data-[state=active]:shadow-sm">
                                <SearchCode className="w-3 h-3 mr-1.5" />
                                CRM Scrape
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="draft" className="p-4 space-y-4 m-0 animate-in fade-in slide-in-from-left-2 duration-200">
                        <div className="space-y-3">
                            <label className="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Tone Selection</label>
                            <div className="grid grid-cols-3 gap-2">
                                {(['professional', 'friendly', 'concise'] as const).map((t) => (
                                    <button
                                        key={t}
                                        onClick={() => setTone(t)}
                                        className={cn(
                                            "px-2 py-2 rounded-lg border text-[10px] font-bold transition-all text-center capitalize",
                                            tone === t 
                                                ? "bg-indigo-50 border-indigo-200 text-indigo-600 shadow-sm" 
                                                : "bg-white border-slate-100 text-slate-500 hover:border-slate-200"
                                        )}
                                    >
                                        {t}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <Button 
                            className="w-full h-10 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs gap-2 shadow-lg shadow-indigo-100 dark:shadow-none"
                            onClick={handleGenerate}
                            disabled={isGenerating}
                        >
                            {isGenerating ? (
                                <Wand2 className="w-3.5 h-3.5 animate-spin" />
                            ) : (
                                <>
                                    <Zap className="w-3.5 h-3.5 fill-current" />
                                    Generate & Insert Draft
                                </>
                            )}
                        </Button>
                        <p className="text-[9px] text-center text-slate-400 font-medium">Draft will be inserted directly into the message box.</p>
                    </TabsContent>

                    <TabsContent value="convert" className="p-4 space-y-4 m-0 animate-in fade-in slide-in-from-right-2 duration-200">
                        <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 space-y-2">
                            <h4 className="text-[11px] font-bold text-slate-700 dark:text-slate-300">Lead Extraction Scrape</h4>
                            <p className="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
                                Analyzes entire thread for names, companies, and project values to pre-fill the Lead Creation modal.
                            </p>
                        </div>
                        
                        <Button 
                            className="w-full h-10 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs gap-2 shadow-lg shadow-indigo-100 dark:shadow-none transition-all group"
                            onClick={() => {
                                onAiConvert();
                                setOpen(false);
                            }}
                        >
                            <SearchCode className="w-3.5 h-3.5 group-hover:scale-110 transition-transform" />
                            Run AI Analysis Scrape
                            <ChevronRight className="w-3 h-3 ml-auto opacity-50" />
                        </Button>
                    </TabsContent>
                </Tabs>
                <div className="bg-slate-50 dark:bg-slate-900/50 py-2 border-t border-slate-100 dark:border-slate-900 flex justify-center">
                    <div className="flex items-center gap-1.5 opacity-50">
                        <Check className="w-2.5 h-2.5 text-emerald-500" />
                        <span className="text-[8px] font-bold uppercase tracking-widest text-slate-500">Professional Intelligence Active</span>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
