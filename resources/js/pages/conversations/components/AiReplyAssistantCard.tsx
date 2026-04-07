import React, { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sparkles, Wand2, RefreshCw, Copy, Check } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { getMockDraft } from '../utils/mockAiData';

interface AiReplyAssistantCardProps {
    onInsertDraft: (body: string) => void;
}

export const AiReplyAssistantCard: React.FC<AiReplyAssistantCardProps> = ({ onInsertDraft }) => {
    const [tone, setTone] = useState<string>('professional');
    const [generating, setGenerating] = useState(false);
    const [currentDraft, setCurrentDraft] = useState<{ body: string } | null>(null);
    const [copied, setCopied] = useState(false);

    const handleGenerate = () => {
        setGenerating(true);
        // Simulate AI thinking
        setTimeout(() => {
            const draft = getMockDraft(tone);
            setCurrentDraft({ body: draft.body });
            setGenerating(false);
            toast.success('Reply draft generated!');
        }, 1200);
    };

    const handleInsert = () => {
        if (currentDraft) {
            onInsertDraft(currentDraft.body);
            toast.success('Draft inserted into composer');
        }
    };

    const handleCopy = () => {
        if (currentDraft) {
            navigator.clipboard.writeText(currentDraft.body.replace(/<[^>]*>/g, ''));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
            toast.success('Copied to clipboard');
        }
    };

    return (
        <Card className="border-primary/20 shadow-sm overflow-hidden mb-4 bg-primary/5 border-dashed">
            <CardHeader className="pb-2 py-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-bold flex items-center gap-2">
                        <Sparkles className="h-4 w-4 text-primary" />
                        Reply Assistant
                    </CardTitle>
                </div>
            </CardHeader>
            <CardContent className="pt-2 space-y-4">
                <div className="flex gap-2">
                    <Select value={tone} onValueChange={setTone}>
                        <SelectTrigger className="h-8 text-xs flex-1 bg-white">
                            <SelectValue placeholder="Select tone" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="professional">Professional</SelectItem>
                            <SelectItem value="friendly">Friendly</SelectItem>
                            <SelectItem value="concise">Concise</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button 
                        size="sm" 
                        className="h-8 text-xs gap-1.5 px-3"
                        disabled={generating}
                        onClick={handleGenerate}
                    >
                        {generating ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <Wand2 className="h-3.5 w-3.5" />}
                        Generate
                    </Button>
                </div>

                {currentDraft && !generating && (
                    <div className="relative group">
                        <div className="text-xs p-3 bg-white border border-primary/10 rounded-lg min-h-[100px] leading-relaxed text-foreground/80 overflow-hidden shadow-inner">
                            <div dangerouslySetInnerHTML={{ __html: currentDraft.body }} />
                        </div>
                        <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button size="icon" variant="outline" className="h-6 w-6 bg-white" onClick={handleCopy}>
                                {copied ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                            </Button>
                        </div>
                    </div>
                )}

                {generating && (
                    <div className="space-y-2 py-4 flex flex-col items-center justify-center text-muted-foreground animate-pulse">
                        <Sparkles className="h-8 w-8 text-primary/40" />
                        <span className="text-[10px] uppercase font-bold tracking-tighter">AI is thinking...</span>
                    </div>
                )}
            </CardContent>
            {currentDraft && !generating && (
                <CardFooter className="pt-0 pb-4">
                    <Button 
                        size="sm" 
                        variant="default" 
                        className="h-8 text-xs w-full shadow-md hover:shadow-lg transition-all"
                        onClick={handleInsert}
                    >
                        Insert into Composer
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
};
