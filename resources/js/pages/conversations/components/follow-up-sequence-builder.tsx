import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { toast } from '@/components/custom-toast';
import {
    Plus,
    Trash2,
    RefreshCw,
    Clock,
    Mail,
    Check,
    XCircle,
    ChevronDown,
    CheckCircle,
    Calendar,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import axios from 'axios';

interface Stage {
    id?: number;
    stage_number: number;
    trigger_type: string;
    delay_days: number;
    subject: string;
    body: string;
    queue_items?: QueueItem[];
}

interface QueueItem {
    id: number;
    status: 'pending' | 'sent' | 'cancelled' | 'skipped';
    scheduled_at: string;
    sent_at: string | null;
    cancelled_reason: string | null;
}

interface Template {
    id: string;
    name: string;
    subject: string;
    body: string;
}

interface Props {
    threadId: number;
}

const TRIGGER_OPTIONS = [
    { value: 'no_reply', label: 'If no reply' },
    { value: 'no_open', label: 'If not opened' },
    { value: 'no_click', label: 'If no click' },
    { value: 'drip', label: 'Always send (drip)' },
];

const MERGE_TAGS = ['{FirstName}', '{LastName}', '{Company}', '{Email}', '{SenderName}'];

export function FollowUpSequenceBuilder({ threadId }: Props) {
    const { t } = useTranslation();
    const [stages, setStages] = useState<Stage[]>([]);
    const [templates, setTemplates] = useState<Template[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isEditing, setIsEditing] = useState(false);

    useEffect(() => {
        fetchStages();
        fetchTemplates();
    }, [threadId]);

    const fetchStages = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('api.conversations.follow_up_stages.index', threadId));
            setStages(response.data.stages || []);
        } catch (error) {
            console.error('Failed to load follow-up stages:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchTemplates = async () => {
        try {
            const response = await axios.get(route('api.conversations.follow_up_templates'));
            setTemplates(response.data.templates || []);
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    };

    const handleAddStage = () => {
        setStages(prev => [
            ...prev,
            {
                stage_number: prev.length + 1,
                trigger_type: 'no_reply',
                delay_days: 3,
                subject: 'Re: Follow Up',
                body: '<p>Hi {FirstName},</p><p>Just following up on my previous email. Would love to hear your thoughts.</p><p>Best,<br/>{SenderName}</p>{TrackingPixel}',
            },
        ]);
        setIsEditing(true);
    };

    const handleApplyTemplate = (index: number, template: Template) => {
        setStages(prev => prev.map((s, i) => (i === index ? { 
            ...s, 
            subject: template.subject,
            body: template.body 
        } : s)));
    };

    const handleRemoveStage = (index: number) => {
        setStages(prev => {
            const updated = prev.filter((_, i) => i !== index);
            return updated.map((s, i) => ({ ...s, stage_number: i + 1 }));
        });
    };

    const handleUpdateStage = (index: number, field: keyof Stage, value: any) => {
        setStages(prev => prev.map((s, i) => (i === index ? { ...s, [field]: value } : s)));
    };

    const insertMergeTag = (index: number, tag: string) => {
        setStages(prev =>
            prev.map((s, i) => (i === index ? { ...s, body: s.body + tag } : s))
        );
    };

    const handleSave = async () => {
        if (stages.length === 0) {
            toast.error(t('Add at least one stage'));
            return;
        }

        setSaving(true);
        try {
            await axios.post(route('api.conversations.follow_up_stages.store', threadId), {
                stages: stages.map((s, i) => ({
                    trigger_type: s.trigger_type,
                    delay_days: s.delay_days,
                    subject: s.subject,
                    body: s.body,
                })),
            });
            toast.success(t('Follow-up sequence saved'));
            setIsEditing(false);
            fetchStages();
        } catch (error: any) {
            const msg = error.response?.data?.message || 'Failed to save';
            toast.error(msg);
        } finally {
            setSaving(false);
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'sent':
                return <Badge className="bg-green-100 text-green-700 text-[10px] px-2 py-0.5"><Check className="h-3 w-3 mr-1" />{t('Sent')}</Badge>;
            case 'pending':
                return <Badge className="bg-amber-100 text-amber-700 text-[10px] px-2 py-0.5"><Clock className="h-3 w-3 mr-1" />{t('Pending')}</Badge>;
            case 'cancelled':
                return <Badge className="bg-gray-100 text-gray-600 text-[10px] px-2 py-0.5"><XCircle className="h-3 w-3 mr-1" />{t('Cancelled')}</Badge>;
            default:
                return <Badge variant="outline" className="text-[10px] px-2 py-0.5">{status}</Badge>;
        }
    };

    if (loading) {
        return (
            <div className="p-8 text-center text-muted-foreground text-sm">
                <RefreshCw className="h-5 w-5 animate-spin mx-auto mb-2" />
                {t('Loading follow-up sequence...')}
            </div>
        );
    }

    return (
        <div className="flex flex-col h-full bg-background">
            <div className="flex items-center justify-between px-4 py-3 bg-muted/20 border-b">
                <div className="flex items-center gap-2">
                    <Mail className="h-4 w-4 text-primary" />
                    <span className="text-xs font-bold text-muted-foreground uppercase tracking-tight">
                        {t('Sequence Configuration')}
                    </span>
                </div>
                {!isEditing ? (
                    <Button 
                        variant="outline" 
                        size="sm" 
                        className="h-8 px-3 text-xs font-semibold shadow-sm" 
                        onClick={() => { if (stages.length === 0) handleAddStage(); else setIsEditing(true); }}
                    >
                        {stages.length === 0 ? t('Start Sequence') : t('Edit Sequence')}
                    </Button>
                ) : (
                    <div className="flex gap-2">
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            className="h-8 px-3 text-xs" 
                            onClick={() => { setIsEditing(false); fetchStages(); }}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button 
                            size="sm" 
                            className="h-8 px-4 text-xs font-bold shadow-md" 
                            onClick={handleSave} 
                            disabled={saving}
                        >
                            {saving ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : t('Save Changes')}
                        </Button>
                    </div>
                )}
            </div>

            <ScrollArea className="flex-1 max-h-[65vh]">
                <div className="p-4 space-y-4">
                    {stages.length === 0 && !isEditing ? (
                        <div className="py-12 text-center">
                            <div className="bg-primary/5 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3">
                                <Clock className="h-6 w-6 text-primary/40" />
                            </div>
                            <p className="text-sm text-muted-foreground max-w-[200px] mx-auto">
                                {t('No automated follow-ups configured for this thread yet.')}
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {stages.map((stage, index) => (
                                <div key={index} className="relative border rounded-xl p-4 bg-muted/5 hover:bg-muted/10 transition-colors space-y-3 group">
                                    <div className="flex items-center justify-between border-b pb-3 mb-1">
                                        <div className="flex items-center gap-2">
                                            <div className="w-6 h-6 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[10px] font-bold">
                                                {index + 1}
                                            </div>
                                            <span className="text-xs font-bold text-foreground">
                                                {t('Stage')} {index + 1}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {stage.queue_items?.map((qi) => (
                                                <div key={qi.id}>{getStatusBadge(qi.status)}</div>
                                            ))}
                                            {isEditing && (
                                                <div className="flex items-center gap-2">
                                                    {templates.length > 0 && (
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="sm" className="h-7 px-2 text-[10px] font-bold text-primary hover:text-primary/80 bg-primary/5">
                                                                    {t('Load Template')}
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-56">
                                                                {templates.map(tmpl => (
                                                                    <DropdownMenuItem key={tmpl.id} onClick={() => handleApplyTemplate(index, tmpl)} className="text-xs py-2">
                                                                        {tmpl.name}
                                                                    </DropdownMenuItem>
                                                                ))}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    )}
                                                    <Button 
                                                        variant="ghost" 
                                                        size="icon" 
                                                        className="h-7 w-7 text-destructive/40 hover:text-destructive hover:bg-destructive/5 transition-colors" 
                                                        onClick={() => handleRemoveStage(index)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {isEditing ? (
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                {/* Trigger */}
                                                <div className="space-y-1.5">
                                                    <label className="text-[10px] font-bold text-muted-foreground uppercase ml-1">{t('Send Trigger')}</label>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="outline" size="sm" className="w-full h-9 text-xs justify-between bg-background px-3 font-medium">
                                                                {TRIGGER_OPTIONS.find(o => o.value === stage.trigger_type)?.label || stage.trigger_type}
                                                                <ChevronDown className="h-4 w-4 opacity-50" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent className="w-[200px]">
                                                            {TRIGGER_OPTIONS.map(opt => (
                                                                <DropdownMenuItem key={opt.value} onClick={() => handleUpdateStage(index, 'trigger_type', opt.value)} className="text-xs py-2">
                                                                    {opt.label}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>

                                                {/* Delay */}
                                                <div className="space-y-1.5">
                                                    <label className="text-[10px] font-bold text-muted-foreground uppercase ml-1">{t('Delay (Days)')}</label>
                                                    <div className="flex items-center">
                                                        <Input
                                                            type="number"
                                                            min={1}
                                                            max={90}
                                                            value={stage.delay_days}
                                                            onChange={(e) => handleUpdateStage(index, 'delay_days', parseInt(e.target.value) || 1)}
                                                            className="h-9 w-full text-xs text-center font-medium bg-background"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Subject */}
                                            <div className="space-y-1.5">
                                                <label className="text-[10px] font-bold text-muted-foreground uppercase ml-1">{t('Email Subject')}</label>
                                                <Input
                                                    value={stage.subject}
                                                    onChange={(e) => handleUpdateStage(index, 'subject', e.target.value)}
                                                    placeholder={t('Re: {Subject}')}
                                                    className="h-9 text-xs font-medium bg-background px-3"
                                                />
                                            </div>

                                            {/* Body */}
                                            <div className="space-y-1.5">
                                                <div className="flex items-center justify-between ml-1">
                                                    <label className="text-[10px] font-bold text-muted-foreground uppercase">{t('Message Content')}</label>
                                                    <span className="text-[9px] text-muted-foreground opacity-60 italic">{t('HTML Supported')}</span>
                                                </div>
                                                <textarea
                                                    value={stage.body}
                                                    onChange={(e) => handleUpdateStage(index, 'body', e.target.value)}
                                                    placeholder={t('Type your follow-up message here...')}
                                                    className="w-full min-h-[120px] text-sm border rounded-lg p-3 resize-none bg-background focus:outline-none focus:ring-1 focus:ring-primary shadow-sm"
                                                />
                                            </div>

                                            {/* Merge Tag Chips */}
                                            <div className="space-y-1.5">
                                                <label className="text-[10px] font-bold text-muted-foreground uppercase ml-1">{t('Personalization Tags')}</label>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {MERGE_TAGS.map(tag => (
                                                        <button
                                                            key={tag}
                                                            type="button"
                                                            onClick={() => insertMergeTag(index, tag)}
                                                            className="text-[10px] px-2.5 py-1 rounded-md bg-primary/5 text-primary border border-primary/10 hover:bg-primary/10 transition-colors font-medium shadow-sm"
                                                        >
                                                            {tag}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-start">
                                            <div className="space-y-1.5">
                                                <div className="text-xs font-bold truncate pr-4">{stage.subject}</div>
                                                <div className="text-[11px] text-muted-foreground line-clamp-2 italic opacity-80" dangerouslySetInnerHTML={{ __html: stage.body.substring(0, 150) }} />
                                            </div>
                                            <div className="flex sm:flex-col items-center sm:items-end gap-2 text-[10px] text-muted-foreground font-semibold bg-muted/30 px-2 py-1 rounded-md">
                                                <div className="flex items-center gap-1">
                                                    <CheckCircle className="h-3 w-3 text-primary" />
                                                    {TRIGGER_OPTIONS.find(o => o.value === stage.trigger_type)?.label}
                                                </div>
                                                <div className="hidden sm:block w-full h-[1px] bg-muted-foreground/10" />
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    {stage.delay_days} {t('days delay')}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}

                            {isEditing && (
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    className="w-full h-10 text-xs gap-2 border-dashed border-2 hover:border-primary hover:text-primary transition-all bg-background shadow-sm" 
                                    onClick={handleAddStage}
                                >
                                    <Plus className="h-4 w-4" />
                                    {t('Add Next Sequential Stage')}
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </ScrollArea>
        </div>
    );
}
