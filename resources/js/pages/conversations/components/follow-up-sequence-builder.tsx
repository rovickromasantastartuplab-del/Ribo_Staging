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
                return <Badge className="bg-green-100 text-green-700 text-[9px] px-1.5 py-0"><Check className="h-2.5 w-2.5 mr-0.5" />{t('Sent')}</Badge>;
            case 'pending':
                return <Badge className="bg-amber-100 text-amber-700 text-[9px] px-1.5 py-0"><Clock className="h-2.5 w-2.5 mr-0.5" />{t('Pending')}</Badge>;
            case 'cancelled':
                return <Badge className="bg-gray-100 text-gray-600 text-[9px] px-1.5 py-0"><XCircle className="h-2.5 w-2.5 mr-0.5" />{t('Cancelled')}</Badge>;
            default:
                return <Badge variant="outline" className="text-[9px] px-1.5 py-0">{status}</Badge>;
        }
    };

    if (loading) {
        return (
            <div className="p-3 text-center text-muted-foreground text-xs">
                <RefreshCw className="h-3.5 w-3.5 animate-spin mx-auto mb-1" />
                {t('Loading sequence...')}
            </div>
        );
    }

    return (
        <div className="border-t">
            <div className="flex items-center justify-between px-3 py-2 bg-muted/30">
                <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                    <Mail className="h-3 w-3" />
                    {t('Auto Follow-ups')}
                </span>
                {!isEditing ? (
                    <Button variant="ghost" size="sm" className="h-6 px-2 text-[10px]" onClick={() => { if (stages.length === 0) handleAddStage(); else setIsEditing(true); }}>
                        {stages.length === 0 ? t('+ Add') : t('Edit')}
                    </Button>
                ) : (
                    <div className="flex gap-1">
                        <Button variant="ghost" size="sm" className="h-6 px-2 text-[10px]" onClick={() => { setIsEditing(false); fetchStages(); }}>
                            {t('Cancel')}
                        </Button>
                        <Button size="sm" className="h-6 px-2 text-[10px]" onClick={handleSave} disabled={saving}>
                            {saving ? <RefreshCw className="h-3 w-3 animate-spin" /> : t('Save')}
                        </Button>
                    </div>
                )}
            </div>

            {stages.length === 0 && !isEditing ? (
                <div className="px-3 py-4 text-center text-muted-foreground text-[10px]">
                    {t('No automated follow-ups configured.')}
                </div>
            ) : (
                <ScrollArea className="max-h-60">
                    <div className="p-2 space-y-2">
                        {stages.map((stage, index) => (
                            <div key={index} className="border rounded-md p-2 bg-background space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] font-bold text-muted-foreground">
                                        {t('Stage')} {index + 1}
                                    </span>
                                    <div className="flex items-center gap-1">
                                        {/* Queue status badges (read-only view) */}
                                        {stage.queue_items?.map((qi) => (
                                            <span key={qi.id}>{getStatusBadge(qi.status)}</span>
                                        ))}
                                        {isEditing && (
                                            <div className="flex items-center gap-1">
                                                {templates.length > 0 && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm" className="h-5 px-1.5 text-[9px] font-bold text-primary hover:text-primary/80">
                                                                {t('Template')}
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-48">
                                                            {templates.map(tmpl => (
                                                                <DropdownMenuItem key={tmpl.id} onClick={() => handleApplyTemplate(index, tmpl)} className="text-xs">
                                                                    {tmpl.name}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                                <Button variant="ghost" size="icon" className="h-5 w-5 text-destructive/60 hover:text-destructive" onClick={() => handleRemoveStage(index)}>
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {isEditing ? (
                                    <>
                                        {/* Trigger */}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="outline" size="sm" className="w-full h-7 text-[10px] justify-between">
                                                    {TRIGGER_OPTIONS.find(o => o.value === stage.trigger_type)?.label || stage.trigger_type}
                                                    <ChevronDown className="h-3 w-3 ml-1" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent className="w-48">
                                                {TRIGGER_OPTIONS.map(opt => (
                                                    <DropdownMenuItem key={opt.value} onClick={() => handleUpdateStage(index, 'trigger_type', opt.value)} className="text-xs">
                                                        {opt.label}
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>

                                        {/* Delay */}
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-[10px] text-muted-foreground shrink-0">{t('Wait')}</span>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={90}
                                                value={stage.delay_days}
                                                onChange={(e) => handleUpdateStage(index, 'delay_days', parseInt(e.target.value) || 1)}
                                                className="h-6 w-14 text-[10px] text-center"
                                            />
                                            <span className="text-[10px] text-muted-foreground">{t('days')}</span>
                                        </div>

                                        {/* Subject */}
                                        <Input
                                            value={stage.subject}
                                            onChange={(e) => handleUpdateStage(index, 'subject', e.target.value)}
                                            placeholder={t('Subject')}
                                            className="h-6 text-[10px]"
                                        />

                                        {/* Body */}
                                        <textarea
                                            value={stage.body}
                                            onChange={(e) => handleUpdateStage(index, 'body', e.target.value)}
                                            placeholder={t('Email body (HTML supported)')}
                                            className="w-full h-16 text-[10px] border rounded-md p-1.5 resize-none bg-background focus:outline-none focus:ring-1 focus:ring-primary"
                                        />

                                        {/* Merge Tag Chips */}
                                        <div className="flex flex-wrap gap-1">
                                            {MERGE_TAGS.map(tag => (
                                                <button
                                                    key={tag}
                                                    type="button"
                                                    onClick={() => insertMergeTag(index, tag)}
                                                    className="text-[9px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors"
                                                >
                                                    {tag}
                                                </button>
                                            ))}
                                        </div>
                                    </>
                                ) : (
                                    <div className="space-y-0.5">
                                        <div className="text-[10px] text-muted-foreground">
                                            <span className="font-medium">{TRIGGER_OPTIONS.find(o => o.value === stage.trigger_type)?.label}</span>
                                            {' · '}{stage.delay_days}d delay
                                        </div>
                                        <div className="text-[10px] truncate">{stage.subject}</div>
                                    </div>
                                )}
                            </div>
                        ))}

                        {isEditing && (
                            <Button variant="outline" size="sm" className="w-full h-7 text-[10px] gap-1" onClick={handleAddStage}>
                                <Plus className="h-3 w-3" />
                                {t('Add Stage')}
                            </Button>
                        )}
                    </div>
                </ScrollArea>
            )}
        </div>
    );
}
