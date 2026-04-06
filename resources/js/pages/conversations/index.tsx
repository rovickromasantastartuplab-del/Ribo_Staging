import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Inbox,
    Send,
    Archive,
    Search,
    MoreVertical,
    User,
    UserPlus,
    X,
    RefreshCw,
    AlertCircle,
    ArrowLeft,
    PenBox,
    Mail,
    Paperclip,
    FileText,
    Download,
    Image as ImageIcon,
    Smile,
    Link as LinkIcon,
    Type,
    Calendar,
    ChevronRight,
    Clock,
    UserCheck,
    CheckCircle,
    Star,
    Bold,
    Italic,
    Underline,
    MoreHorizontal,
    CornerDownLeft,
    Check,
} from 'lucide-react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { cn } from '@/lib/utils';
import { ActivityStream } from '@/components/ActivityStream/ActivityStream';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { ConversationsCalendar, ConversationsCalendarHandle } from './components/conversations-calendar';
import { FollowUpSequenceBuilder } from './components/follow-up-sequence-builder';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import { CrudFormModal } from '@/components/CrudFormModal';
import { hasPermission } from '@/utils/authorization';
import axios from 'axios';
import { formatDistanceToNow, format } from 'date-fns';
import { getEcho } from '@/utils/echo';
import { sanitizeHtml } from '@/utils/sanitize-html';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuLabel,
    DropdownMenuCheckboxItem,
} from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { DatePicker } from '@/components/ui/date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Briefcase, TrendingUp, ExternalLink, ChevronDown, DollarSign, ChevronUp as ChevronUpIcon } from 'lucide-react';

/* ── helpers ───────────────────────────────────────────────── */
const parseUTC = (dateStr: string) => {
    if (!dateStr) return new Date();
    return dateStr.endsWith('Z') ? new Date(dateStr) : new Date(dateStr + 'Z');
};

/** Dot colors aligned with `leads/show.tsx` stream type styling (getActivityColor / badge semantics). */
const getLeadStreamPreviewDotClass = (activity: { activity_type?: string; field_changed?: string | null }) => {
    const ty = activity.activity_type || '';
    switch (ty) {
        case 'created':
            return 'bg-emerald-500 ring-emerald-50';
        case 'updated':
            return activity.field_changed === 'lead_status_id' || activity.field_changed === 'opportunity_stage_id'
                ? 'bg-violet-500 ring-violet-50'
                : 'bg-blue-500 ring-blue-50';
        case 'assigned':
            return 'bg-purple-500 ring-purple-50';
        case 'converted':
            return 'bg-orange-500 ring-orange-50';
        case 'comment':
            return 'bg-indigo-500 ring-indigo-50';
        case 'email':
            return 'bg-sky-500 ring-sky-50';
        case 'message':
            return 'bg-teal-500 ring-teal-50';
        default:
            return 'bg-muted-foreground ring-muted/40';
    }
};

const timeAgo = (dateStr: string) => {
    if (!dateStr) return '';
    try { return formatDistanceToNow(parseUTC(dateStr), { addSuffix: true }); }
    catch { return ''; }
};

const timeAgoShort = (dateStr: string) => {
    if (!dateStr) return '';
    try { return formatDistanceToNow(parseUTC(dateStr), { addSuffix: false }); }
    catch { return ''; }
};

/* ── Folder tab selector (works on all sizes) ──────────────── */
const FolderTabs = ({ selectedFolder, onSelect, unreadCount, t, onCompose, onCanCompose }: any) => {
    const folders = [
        { key: 'inbox', icon: Inbox, label: t('Inbox'), count: unreadCount },
        { key: 'my_assignments', icon: UserCheck, label: t('My Assignments'), count: 0 },
        { key: 'unassigned', icon: Archive, label: t('Unassigned'), count: 0 },
        { key: 'sent', icon: Send, label: t('Sent'), count: 0 },
        { key: 'calendar', icon: Calendar, label: t('Follow-up Calendar'), count: 0 },
        { key: 'closed', icon: CheckCircle, label: t('Closed'), count: 0 },
        { key: 'archive', icon: Archive, label: t('Archive'), count: 0 },
    ];
    return (
        <div className="flex gap-1 p-2 overflow-x-auto scrollbar-hide flex-nowrap items-center">
            {onCanCompose && (
                <Button size="sm" onClick={onCompose} className="h-7 px-3 text-xs gap-1.5 shrink-0 mr-1.5 text-primary-foreground">
                    <PenBox className="h-3.5 w-3.5" />
                    {t('Compose')}
                </Button>
            )}
            {folders.map(f => (
                <button
                    key={f.key}
                    onClick={() => onSelect(f.key)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap transition-colors ${selectedFolder === f.key
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-muted'
                        }`}
                >
                    <f.icon className="h-3.5 w-3.5" />
                    {f.label}
                    {f.count > 0 && (
                        <span className={`text-xs px-1 py-0 rounded-full ${selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20'
                            }`}>{f.count}</span>
                    )}
                </button>
            ))}
        </div>
    );
};

/* ── Full sidebar for xl+ screens ──────────────────────────── */
const FolderSidebar = ({ selectedFolder, onSelect, unreadCount, t, isSyncing, onSync, onCompose, onCanCompose, isCollapsed, onToggleCollapse }: any) => (
    <div className={`hidden xl:flex transition-all duration-300 border-r flex-col bg-muted/30 shrink-0 ${isCollapsed ? 'w-16 items-center px-1' : 'w-48'}`}>
        <div className="p-3 flex-1 w-full">
            <div className={`flex items-center mb-3 ${isCollapsed ? 'justify-center flex-col gap-2' : 'justify-between px-1'}`}>
                {isCollapsed ? (
                    <Button variant="ghost" size="icon" className="h-8 w-8 hover:bg-muted" onClick={onToggleCollapse} title={t('Expand')}>
                        <MoreHorizontal className="h-4 w-4" />
                    </Button>
                ) : (
                    <button onClick={onToggleCollapse} className="text-sm font-semibold hover:text-primary transition-colors cursor-pointer text-left focus:outline-none flex-1 truncate" title={t('Collapse')}>
                        {t('Conversations')}
                    </button>
                )}

                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={onSync} disabled={isSyncing} title={t('Sync')}>
                    <RefreshCw className={`h-3.5 w-3.5 ${isSyncing ? 'animate-spin text-primary' : ''}`} />
                </Button>
            </div>

            {onCanCompose && (
                <Button
                    className={`nav-compose w-full mb-4 shadow-sm transition-all ${isCollapsed ? 'h-10 p-0 rounded-xl flex items-center justify-center' : 'h-8 text-xs font-semibold gap-1.5'}`}
                    onClick={onCompose}
                    title={t('Compose')}
                >
                    <PenBox className={isCollapsed ? "h-5 w-5" : "h-3.5 w-3.5"} />
                    {!isCollapsed && t('Compose')}
                </Button>
            )}

            <div className="space-y-1 w-full">
                {[
                    { key: 'inbox', icon: Inbox, label: t('Inbox'), count: unreadCount },
                    { key: 'my_assignments', icon: UserCheck, label: t('My Assignments'), count: 0 },
                    { key: 'unassigned_staff', icon: UserPlus, label: t('Unassigned Staff'), count: 0 },
                    { key: 'sent', icon: Send, label: t('Sent'), count: 0 },
                    { key: 'calendar', icon: Calendar, label: t('Follow-up Calendar'), count: 0 },
                    { key: 'closed', icon: CheckCircle, label: t('Closed'), count: 0 },
                    { key: 'archive', icon: Archive, label: t('Archive'), count: 0 },
                ].map(f => (
                    <button
                        key={f.key}
                        onClick={() => onSelect(f.key)}
                        title={isCollapsed ? f.label : undefined}
                        className={`w-full relative flex items-center rounded-md transition-colors ${isCollapsed
                                ? 'justify-center h-10 mb-1'
                                : 'justify-between px-2.5 py-1.5 text-xs'
                            } ${selectedFolder === f.key
                                ? 'bg-primary/10 text-primary font-medium'
                                : 'hover:bg-muted text-muted-foreground'
                            }`}
                    >
                        <span className={`flex items-center ${isCollapsed ? 'justify-center w-full' : 'gap-2'}`}>
                            <f.icon className={isCollapsed ? "h-[18px] w-[18px]" : "h-3.5 w-3.5"} />
                            {!isCollapsed && f.label}
                        </span>
                        {!isCollapsed && f.count > 0 && (
                            <span className={`text-xs px-1.5 py-0 rounded-full ${selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20 text-muted-foreground'}`}>
                                {f.count}
                            </span>
                        )}
                        {isCollapsed && f.count > 0 && (
                            <span className="absolute top-1.5 right-1.5 bg-primary text-primary-foreground text-[8px] font-bold w-3.5 h-3.5 flex items-center justify-center rounded-full border shadow-sm">
                                {f.count > 99 ? '99+' : f.count}
                            </span>
                        )}
                    </button>
                ))}
            </div>
        </div>
    </div>
);

/* ── Main component ────────────────────────────────────────── */
export default function ConversationsIndex({ gmailAccount, companyId, isOwner, unreadCount: initialUnreadCount, selectedThreadId }: { gmailAccount: any, companyId: number, isOwner: boolean, unreadCount?: number, selectedThreadId?: number | null }) {
    const { t } = useTranslation();
    const { auth, leadStatuses = [], opportunityStages = [], leadSources = [], accountIndustries = [], campaigns = [], users = [] } = usePage<any>().props;
    const permissions = auth?.permissions || [];
    const canCompose = isOwner || hasPermission(permissions, 'send-conversations');
    const canManage = isOwner || hasPermission(permissions, 'manage-conversations');
    const canEditLeadStatus = isOwner || hasPermission(permissions, 'edit-leads');
    const canEditOpportunityStage = isOwner || hasPermission(permissions, 'edit-opportunities');
    const isStaff = auth?.user?.type === 'staff';
    const [selectedFolder, setSelectedFolder] = useState('inbox');
    const [threads, setThreads] = useState<any[]>([]);
    const [selectedThread, setSelectedThread] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [isSyncing, setIsSyncing] = useState(false);
    const [showContactSidebar, setShowContactSidebar] = useState(false);
    const [replyBody, setReplyBody] = useState('');
    const [replyCc, setReplyCc] = useState('');
    const [replyBcc, setReplyBcc] = useState('');
    const [showReplyCcBcc, setShowReplyCcBcc] = useState(false);
    const [submittingReply, setSubmittingReply] = useState(false);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount || 0);
    const [searchQuery, setSearchQuery] = useState('');
    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
    // Pagination states
    const [threadPage, setThreadPage] = useState(1);
    const [hasMoreThreads, setHasMoreThreads] = useState(false);

    // Feature states
    const [companyUsers, setCompanyUsers] = useState<any[]>([]);
    // Calendar pane drag-resize width
    const [calendarWidth, setCalendarWidth] = useState(340);
    const isResizingCalendar = React.useRef(false);
    const resizeStartX = React.useRef(0);
    const resizeStartWidth = React.useRef(0);

    const handleCalendarResizeMouseDown = (e: React.MouseEvent) => {
        isResizingCalendar.current = true;
        resizeStartX.current = e.clientX;
        resizeStartWidth.current = calendarWidth;
        const onMouseMove = (ev: MouseEvent) => {
            if (!isResizingCalendar.current) return;
            const delta = ev.clientX - resizeStartX.current;
            const next = Math.max(240, Math.min(560, resizeStartWidth.current + delta));
            setCalendarWidth(next);
        };
        const onMouseUp = () => {
            isResizingCalendar.current = false;
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
        };
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    };
    const [updatingMetadata, setUpdatingMetadata] = useState(false);

    // CRM Sidebar state
    const [activeSidebarSection, setActiveSidebarSection] = useState<'lead' | 'opportunities' | 'activity'>('lead');
    const [expandedOpportunityId, setExpandedOpportunityId] = useState<number | null>(null);
    const [localLeadStatuses, setLocalLeadStatuses] = useState<Record<number, string>>({});
    const [localOppStatuses, setLocalOppStatuses] = useState<Record<number, string>>({});
    const [savingLeadId, setSavingLeadId] = useState<number | null>(null);
    const [savingOppId, setSavingOppId] = useState<number | null>(null);

    // Internal thread message pagination
    const [messagesPage, setMessagesPage] = useState(1);
    const [hasMoreMessages, setHasMoreMessages] = useState(false);
    const [loadingMoreMessages, setLoadingMoreMessages] = useState(false);

    const [showCompose, setShowCompose] = useState(false);
    const [showFollowUpModal, setShowFollowUpModal] = useState(false);
    const [composeTo, setComposeTo] = useState('');
    const [composeCc, setComposeCc] = useState('');
    const [composeBcc, setComposeBcc] = useState('');
    const [composeSubject, setComposeSubject] = useState('');
    const [composeBody, setComposeBody] = useState('');
    const [isComposing, setIsComposing] = useState(false);
    const [showComposeCcBcc, setShowComposeCcBcc] = useState(false);
    const [showFormatting, setShowFormatting] = useState(false);

    // Tiptap Editor for Compose Modal
    const composeEditor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-primary underline'
                }
            }),
        ],
        content: composeBody,
        editorProps: {
            attributes: {
                class: 'prose prose-sm focus:outline-none max-w-none min-h-[15.625rem] p-6 text-sm leading-relaxed'
            }
        },
        onUpdate: ({ editor }) => {
            setComposeBody(editor.getHTML());
        },
    });

    // Tiptap Editor for Main Reply Box
    const replyEditor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-primary underline'
                }
            }),
        ],
        content: replyBody,
        editorProps: {
            attributes: {
                class: 'prose prose-sm focus:outline-none max-w-none min-h-[3.75rem] lg:min-h-[5rem] p-2.5 text-sm leading-relaxed'
            }
        },
        onUpdate: ({ editor }) => {
            setReplyBody(editor.getHTML());
        },
    });

    const [showReplyFormatting, setShowReplyFormatting] = useState(false);

    // Inline Reply Feature States
    const [activeReplyMessage, setActiveReplyMessage] = useState<any>(null);
    const [replyCcList, setReplyCcList] = useState<string[]>([]);
    const [replyBccList, setReplyBccList] = useState<string[]>([]);
    const [ccInput, setCcInput] = useState('');
    const [bccInput, setBccInput] = useState('');

    // Dynamic Unicode Emoji Engine
    const getEmojiRange = (start: number, end: number) => {
        const emojis = [];
        for (let i = start; i <= end; i++) {
            emojis.push(String.fromCodePoint(i));
        }
        return emojis;
    };

    const emojiCategories = [
        { name: 'Smileys', emojis: getEmojiRange(0x1F600, 0x1F64F) },
        { name: 'Gestures', emojis: ['👋', '🤚', '🖐', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏'] },
        { name: 'Activities', emojis: getEmojiRange(0x1F3A0, 0x1F3C4) },
        { name: 'Symbols', emojis: ['❤️', '✨', '🔥', '✅', '❌', '⚠️', '💯', '💢', '♻️', '📢', '🔔', '🔒', '🔓', '📍', '✉️', '📞'] },
    ];

    const EmojiPicker = ({ onSelect, disabled }: { onSelect: (emoji: string) => void, disabled?: boolean }) => (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors"
                    disabled={disabled}
                >
                    <Smile className="h-3.5 w-3.5" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-0 overflow-hidden border shadow-xl" align="start">
                <div className="flex flex-col h-80">
                    <div className="px-3 py-2 border-b bg-muted/5">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Select Emoji</span>
                    </div>
                    <ScrollArea className="flex-1 p-2">
                        <div className="space-y-4">
                            {emojiCategories.map(cat => (
                                <div key={cat.name} className="space-y-1.5">
                                    <h4 className="text-[10px] font-bold text-muted-foreground/70 uppercase px-1">{cat.name}</h4>
                                    <div className="grid grid-cols-7 gap-1">
                                        {cat.emojis.map(emoji => (
                                            <button
                                                key={emoji}
                                                onClick={() => onSelect(emoji)}
                                                className="h-8 w-8 flex items-center justify-center hover:bg-muted rounded text-lg transition-transform hover:scale-125 active:scale-95"
                                            >
                                                {emoji}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </ScrollArea>
                </div>
            </PopoverContent>
        </Popover>
    );

    // Sync external composeBody changes if any (though mostly we use editor)
    useEffect(() => {
        if (composeEditor && composeBody === '') {
            composeEditor.commands.setContent('');
        }
    }, [composeBody, composeEditor]);

    useEffect(() => {
        if (replyEditor && replyBody === '') {
            replyEditor.commands.setContent('');
        }
    }, [replyBody, replyEditor]);
    const [composeFiles, setComposeFiles] = useState<File[]>([]);
    const [replyFiles, setReplyFiles] = useState<File[]>([]);
    const composeFileRef = React.useRef<HTMLInputElement>(null);
    const replyFileRef = React.useRef<HTMLInputElement>(null);
    const threadObserverTarget = React.useRef<HTMLDivElement>(null);
    const messagesEndRef = React.useRef<HTMLDivElement>(null);
    const messagesTopObserverTarget = React.useRef<HTMLDivElement>(null);
    const scrollViewportRef = React.useRef<HTMLDivElement>(null);

    const { post: inertiaPost } = useForm({});

    const [isLeadModalOpen, setIsLeadModalOpen] = useState(false);
    const [leadInitialData, setLeadInitialData] = useState<any>(null);

    const handleAddAsLead = () => {
        if (!selectedThread) return;

        // Find the external participant (not the synced Gmail account)
        const me = gmailAccount?.email;
        const externalParticipant = selectedThread.participants?.find((p: string) => !p.includes(me)) || selectedThread.participants?.[0];

        let name = '';
        let email = '';

        if (externalParticipant) {
            // Check if format is "Name <email@example.com>"
            const match = externalParticipant.match(/(.*)<(.*)>/);
            if (match) {
                name = match[1].trim();
                email = match[2].trim();
            } else {
                email = externalParticipant.trim();
                // If it's just an email, use the part before @ as a fallback name if needed, 
                // but usually better to leave Name blank for the user to fill or use email.
                name = email.split('@')[0];
            }
        }

        setLeadInitialData({
            name: name,
            email: email,
            email_thread_id: selectedThread.id
        });
        setIsLeadModalOpen(true);
    };

    // Observer for loading earlier messages (top of list)
    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMoreMessages && !loadingMoreMessages && selectedThread) {
                    fetchEarlierMessages();
                }
            },
            { threshold: 0.1 }
        );

        if (messagesTopObserverTarget.current) {
            observer.observe(messagesTopObserverTarget.current);
        }

        return () => observer.disconnect();
    }, [hasMoreMessages, loadingMoreMessages, selectedThread, messagesPage]);

    const handleLinkToLead = (leadId: number) => {
        toast.loading(t('Linking thread to lead...'));
        router.post(route('api.conversations.link_to_lead', selectedThread.id), {
            lead_id: leadId
        }, {
            onSuccess: (page: any) => {
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                }
                // Refresh threads to show the new Lead badge
                fetchThreads(false);
            },
            onError: (errors: any) => {
                toast.dismiss();
                toast.error(t('Failed to link lead'));
                console.error(errors);
            }
        });
    };

    const handleSelectThreadById = (id: number) => {
        setLoading(true);
        axios.get(route('api.conversations.show', id))
            .then(r => {
                setSelectedThread(r.data.thread);
            })
            .catch(err => {
                console.error('Failed to load thread from calendar:', err);
                toast.error(t('Failed to load conversation'));
            })
            .finally(() => setLoading(false));
    };

    const handleLeadFormSubmit = (formData: any) => {
        toast.loading(t('Creating lead and linking thread...'));

        router.post(route('leads.store'), {
            ...formData,
            email_thread_id: selectedThread?.id
        }, {
            onSuccess: (page: any) => {
                setIsLeadModalOpen(false);
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                }
                if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
                // Clear selection and refresh threads to show the new Lead badge/move out of unassigned
                setSelectedThread(null);
                fetchThreads(false);
            },
            onError: (errors: any) => {
                toast.dismiss();
                toast.error(t('Failed to create lead'));
                console.error(errors);
            }
        });
    };

    // Ref for real-time refresh
    const selectedThreadIdRef = React.useRef<number | null>(null);
    const calendarRef = React.useRef<ConversationsCalendarHandle>(null);
    useEffect(() => { selectedThreadIdRef.current = selectedThread?.id || null; }, [selectedThread?.id]);

    useEffect(() => {
        setThreadPage(1);
        fetchThreads(false);

        if (!companyId) return;

        const channel = getEcho().private(`company.${companyId}`)
            .listen('.gmail.sync.completed', (data: any) => {
                if (gmailAccount && data.gmailAccountId == gmailAccount.id) {
                    fetchThreads(false, true);
                    if (selectedThreadIdRef.current) {
                        axios.get(route('api.conversations.show', selectedThreadIdRef.current))
                            .then(r => {
                                const newThread = r.data.thread;
                                setSelectedThread((prev: any) => {
                                    if (prev && prev.id === newThread.id) {
                                        return { ...prev, ...newThread };
                                    }
                                    return newThread;
                                });
                            })
                            .catch(err => console.error('Silent refresh failed:', err));
                    }
                }
            });
        return () => { channel.stopListening('.gmail.sync.completed'); };
    }, [selectedFolder, gmailAccount?.id, companyId, searchQuery]);

    // Handle deep linking to a specific thread on mount
    useEffect(() => {
        if (selectedThreadId) {
            setLoading(true);
            axios.get(route('api.conversations.show', selectedThreadId))
                .then(r => {
                    setSelectedThread(r.data.thread);
                })
                .catch(err => {
                    console.error('Failed to load deep-linked thread:', err);
                    toast.error(t('Failed to load conversation'));
                })
                .finally(() => setLoading(false));
        }
    }, [selectedThreadId]);

    useEffect(() => {
        if (!companyId) return;

        // Fetch users for assignment dropdown
        axios.get(route('users.index', { api: true }))
            .then(r => setCompanyUsers(r.data.data || []))
            .catch(err => console.error('Failed to fetch users:', err));
    }, [companyId]);
    // Thread Infinite Scroll Observer
    useEffect(() => {
        const observer = new IntersectionObserver(
            entries => {
                if (entries[0].isIntersecting && hasMoreThreads && !loading) {
                    fetchThreads(true);
                }
            },
            { threshold: 0.1 }
        );
        if (threadObserverTarget.current) observer.observe(threadObserverTarget.current);
        return () => observer.disconnect();
    }, [hasMoreThreads, loading]);


    const fetchThreads = async (append = false, silent = false) => {
        if (!silent) {
            setLoading(true);
            if (!append) setThreads([]);
        }
        try {
            const page = append ? threadPage + 1 : 1;
            const params: any = { folder: selectedFolder, page };
            if (searchQuery.trim()) params.search = searchQuery.trim();

            const response = await axios.get(route('api.conversations.threads', params));
            const { threads: threadsData, unread_count } = response.data;
            const data = threadsData.data;
            const current_page = threadsData.current_page;
            const last_page = threadsData.last_page;

            if (append) {
                setThreads(prev => {
                    const combined = [...prev, ...data];
                    // Unique by ID
                    const unique = Array.from(new Map<number, any>(combined.map((item: any) => [item.id, item])).values());
                    return unique;
                });
            } else {
                setThreads(data);
            }

            if (unread_count !== undefined) {
                setUnreadCount(unread_count);
            }

            setThreadPage(current_page);
            setHasMoreThreads(current_page < last_page);

            // If we reached the end of local threads in inbox/sent, try syncing more from Gmail
            if (append && current_page >= last_page && (selectedFolder === 'inbox' || selectedFolder === 'sent')) {
                try {
                    const syncResponse = await axios.post(route('api.conversations.sync_inbox_more'));
                    if (syncResponse.data.success && syncResponse.data.stats?.synced > 0) {
                        const newResponse = await axios.get(route('api.conversations.threads', params));
                        const { threads: newThreadsData } = newResponse.data;

                        setThreads(prev => {
                            const combined = [...prev, ...newThreadsData.data];
                            return Array.from(new Map<number, any>(combined.map((item: any) => [item.id, item])).values());
                        });

                        setThreadPage(newThreadsData.current_page);
                        setHasMoreThreads(newThreadsData.current_page < newThreadsData.last_page);
                    }
                } catch (syncError) {
                    console.error('Failed to sync more inbox threads:', syncError);
                }
            }
        } catch (error) {
            console.error('Failed to fetch threads:', error);
            if (!silent) toast.error(t('Failed to fetch threads'));
        } finally {
            if (!silent) setLoading(false);
        }
    };

    const handleSync = () => {
        setIsSyncing(true);
        inertiaPost(route('settings.gmail.sync'), {
            preserveScroll: true,
            onSuccess: () => { setIsSyncing(false); fetchThreads(); },
            onError: () => { setIsSyncing(false); toast.error(t('Failed to synchronize inbox')); }
        });
    };

    const jsonCrmHeaders = { headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };

    useEffect(() => {
        setLocalLeadStatuses({});
        setLocalOppStatuses({});
    }, [selectedThread?.id]);

    const persistLeadStatus = async (lead: any, statusName: string) => {
        const leadId = lead.id;
        const ls = leadStatuses.find((x: any) => x.name === statusName);
        if (!ls) {
            toast.error(t('Invalid status'));
            return;
        }
        const previousName = localLeadStatuses[leadId] ?? lead.lead_status?.name ?? lead.leadStatus?.name;
        setLocalLeadStatuses((prev) => ({ ...prev, [leadId]: statusName }));
        setSavingLeadId(leadId);
        try {
            const { data } = await axios.put(
                route('leads.update-status', leadId),
                { lead_status_id: ls.id },
                jsonCrmHeaders
            );
            const updated = data.lead;
            setSelectedThread((prev: any) => {
                if (!prev?.leads) return prev;
                return {
                    ...prev,
                    leads: prev.leads.map((l: any) =>
                        l.id === leadId
                            ? {
                                ...l,
                                ...updated,
                                lead_status: updated.lead_status ?? updated.leadStatus ?? l.lead_status,
                                leadStatus: updated.leadStatus ?? updated.lead_status ?? l.leadStatus,
                            }
                            : l
                    ),
                };
            });
            setLocalLeadStatuses((prev) => {
                const next = { ...prev };
                delete next[leadId];
                return next;
            });
            if (data.message) toast.success(data.message);
        } catch (e: any) {
            if (previousName !== undefined && previousName !== null) {
                setLocalLeadStatuses((prev) => ({ ...prev, [leadId]: previousName }));
            } else {
                setLocalLeadStatuses((prev) => {
                    const next = { ...prev };
                    delete next[leadId];
                    return next;
                });
            }
            const msg = e?.response?.data?.message;
            toast.error(msg ? String(msg) : t('Failed to update lead status'));
        } finally {
            setSavingLeadId(null);
        }
    };

    const persistOpportunityStage = async (opp: any, stageName: string) => {
        const oppId = opp.id;
        const st = opportunityStages.find((x: any) => x.name === stageName);
        if (!st) {
            toast.error(t('Invalid stage'));
            return;
        }
        const previousName = localOppStatuses[oppId] ?? opp.opportunity_stage?.name ?? opp.opportunityStage?.name;
        setLocalOppStatuses((prev) => ({ ...prev, [oppId]: stageName }));
        setSavingOppId(oppId);
        try {
            const { data } = await axios.put(
                route('opportunities.update-status', oppId),
                { opportunity_stage_id: st.id },
                jsonCrmHeaders
            );
            const updated = data.opportunity;
            setSelectedThread((prev: any) => {
                if (!prev?.leads) return prev;
                return {
                    ...prev,
                    leads: prev.leads.map((l: any) => ({
                        ...l,
                        opportunities: (l.opportunities ?? []).map((o: any) =>
                            o.id === oppId
                                ? {
                                    ...o,
                                    ...updated,
                                    opportunity_stage: updated.opportunity_stage ?? updated.opportunityStage ?? o.opportunity_stage,
                                    opportunityStage: updated.opportunityStage ?? updated.opportunity_stage ?? o.opportunityStage,
                                }
                                : o
                        ),
                    })),
                };
            });
            setLocalOppStatuses((prev) => {
                const next = { ...prev };
                delete next[oppId];
                return next;
            });
            if (data.message) toast.success(data.message);
        } catch (e: any) {
            if (previousName !== undefined && previousName !== null) {
                setLocalOppStatuses((prev) => ({ ...prev, [oppId]: previousName }));
            } else {
                setLocalOppStatuses((prev) => {
                    const next = { ...prev };
                    delete next[oppId];
                    return next;
                });
            }
            const msg = e?.response?.data?.message;
            toast.error(msg ? String(msg) : t('Failed to update opportunity stage'));
        } finally {
            setSavingOppId(null);
        }
    };

    const handleSelectThread = async (thread: any, page = 1) => {
        // Prevent clearing the UI if we're just refreshing the current thread (e.g., after reply)
        if (page === 1 && (!selectedThread || selectedThread.id !== thread.id)) {
            setLoading(true);
        } else if (page > 1) {
            setLoadingMoreMessages(true);
        }

        try {
            const response = await axios.get(route('api.conversations.show', thread.id), {
                params: { page }
            });

            const newThread = response.data.thread;
            const pagination = response.data.messages_pagination;
            const unread_count = response.data.unread_count;

            if (unread_count !== undefined) {
                setUnreadCount(unread_count);
            }

            if (page === 1) {
                setSelectedThread((prev: any) => {
                    // 100% Fix: Merge with previous state to prevent blanking if relations or attributes flicker
                    if (prev && prev.id === newThread.id) {
                        return { ...prev, ...newThread };
                    }
                    return newThread;
                });
                setMessagesPage(1);
                setHasMoreMessages(pagination.has_more);
                // Re-add a small delay for the initial load to ensure flex-col-reverse layout is ready
                setTimeout(() => scrollToBottom('auto'), 50);
            } else {
                // Infinite scroll up (logically at end of the array in col-reverse):
                setSelectedThread((prev: any) => {
                    if (!prev || prev.id !== newThread.id) return prev;
                    return {
                        ...prev,
                        messages: [...prev.messages, ...newThread.messages]
                    };
                });
                setMessagesPage(page);
                setHasMoreMessages(pagination.has_more);
            }
        } catch {
            toast.error(t('Failed to load conversation details'));
        } finally {
            setLoading(false);
            setLoadingMoreMessages(false);
        }
    };

    const scrollToBottom = (behavior: ScrollBehavior = 'smooth') => {
        messagesEndRef.current?.scrollIntoView({ behavior });
    };

    const fetchEarlierMessages = () => {
        if (!selectedThread || !hasMoreMessages || loadingMoreMessages) return;
        handleSelectThread(selectedThread, messagesPage + 1);
    };

    const handleInlineReply = (msg: any) => {
        setActiveReplyMessage(msg);
        setReplyCcList([]);
        setReplyBccList([]);
        setShowReplyCcBcc(true);
        scrollToBottom();
        replyEditor?.commands.focus();
    };

    const handleSendReply = async () => {
        if (!replyBody.trim()) return;
        setSubmittingReply(true);
        try {
            const formData = new FormData();
            formData.append('body', replyBody);

            const finalCc = replyCcList.join(', ');
            if (finalCc) formData.append('cc', finalCc);

            const finalBcc = replyBccList.join(', ');
            if (finalBcc) formData.append('bcc', finalBcc);

            replyFiles.forEach((file) => formData.append('attachments[]', file));

            if (activeReplyMessage) {
                formData.append('reply_to_message_id', activeReplyMessage.id.toString());
                formData.append('primary_to', activeReplyMessage.from_email);
            }

            await axios.post(route('api.conversations.reply', selectedThread.id), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toast.success(t('Reply sent successfully'));
            setReplyBody('');
            setReplyCcList([]);
            setReplyBccList([]);
            setShowReplyCcBcc(false);
            setActiveReplyMessage(null);
            replyEditor?.commands.setContent('');
            setReplyFiles([]);
            // Refresh list to show updated snippet/timestamp
            fetchThreads(false, true);
            handleSelectThread(selectedThread);
        } catch (error: any) {
            toast.error(error.response?.data?.error || t('Failed to send reply'));
        } finally {
            setSubmittingReply(false);
        }
    };

    const handleSendNewEmail = async () => {
        if (!composeTo.trim() || !composeSubject.trim() || !composeBody.trim()) {
            toast.error(t('Please fill in all fields'));
            return;
        }
        setIsComposing(true);
        try {
            const formData = new FormData();
            formData.append('to', composeTo);
            formData.append('subject', composeSubject);
            formData.append('body', composeBody);
            if (composeCc) formData.append('cc', composeCc);
            if (composeBcc) formData.append('bcc', composeBcc);
            composeFiles.forEach((file) => formData.append('attachments[]', file));
            await axios.post(route('api.conversations.compose'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toast.success(t('Email sent successfully'));
            setShowCompose(false);
            setComposeTo('');
            setComposeCc('');
            setComposeBcc('');
            setComposeSubject('');
            setComposeBody('');
            setComposeFiles([]);
            setShowComposeCcBcc(false);
        } catch (error: any) {
            toast.error(error.response?.data?.error || t('Failed to send email'));
        } finally {
            setIsComposing(false);
        }
    };

    const handleBack = () => {
        setSelectedThread(null);
        setActiveReplyMessage(null);
        setShowContactSidebar(false);
    };

    const handleUpdateMetadata = async (updates: any) => {
        if (!selectedThread) return;
        setUpdatingMetadata(true);
        try {
            const response = await axios.post(route('api.conversations.update', selectedThread.id), updates);
            setSelectedThread(response.data.thread);
            // Refresh thread list to show new metadata (like closed)
            fetchThreads(false, true);
            toast.success(t('Conversation updated'));
        } catch (error) {
            toast.error(t('Failed to update conversation'));
        } finally {
            setUpdatingMetadata(false);
        }
    };

    const handleAssignUsers = async (userIds: number[]) => {
        if (!selectedThread) return;
        setUpdatingMetadata(true);
        try {
            const response = await axios.post(route('api.conversations.assign', selectedThread.id), { user_ids: userIds });
            setSelectedThread(response.data.thread);
            toast.success(t('Assignments updated'));
        } catch (error) {
            toast.error(t('Failed to update assignments'));
        } finally {
            setUpdatingMetadata(false);
        }
    };

    /* ─── Determine which view to show on mobile ─── */
    // Mobile: show thread list when no thread is selected, show detail when one is.
    // Tablet/desktop: show both side-by-side.
    const showListPane = !selectedThread; // Always show on md+ via CSS
    const showDetailPane = !!selectedThread; // Always show on md+ via CSS

    return (
        <PageTemplate
            title={t('Conversations')}
            description={t('Unified inbox for all relationships')}
            url="/conversations"
            noPadding
            noOuterPadding
            hideHeader
        >
            <Head title={t('Conversations Hub')} />

            {/* 
                Main container: fills available height (edge-to-edge under PageTemplate title row).
                AppLayout: top nav (~56px) + breadcrumb (~40px) + PageTemplate title row (~52px).
                noOuterPadding removes page p-4/md:p-6/lg:p-8 — no extra vertical gutter here.
                Total overhead ≈ 56 + 40 = ~96px; use 116px to account for gaps/borders.
            */}
            <div className="flex flex-col h-screen overflow-hidden lg:h-[calc(100vh-116px)] min-h-[25rem] bg-background border-b border-border relative">

                {/* Mobile folder tabs: visible below xl where the sidebar is hidden */}
                <div className="xl:hidden border-b shrink-0">
                    <FolderTabs
                        selectedFolder={selectedFolder}
                        onSelect={setSelectedFolder}
                        unreadCount={unreadCount}
                        t={t}
                        onCompose={() => setShowCompose(true)}
                        onCanCompose={canCompose}
                    />
                </div>

                {/* Main flex row: sidebar + list + detail */}
                <div className="flex flex-col md:flex-row flex-1 min-h-0 overflow-y-auto lg:overflow-hidden">

                    {/* Pane 1: Folder sidebar (xl+ only) */}
                    <FolderSidebar
                        selectedFolder={selectedFolder}
                        onSelect={setSelectedFolder}
                        unreadCount={unreadCount}
                        t={t}
                        isSyncing={isSyncing}
                        onSync={handleSync}
                        onCompose={() => setShowCompose(true)}
                        onCanCompose={canCompose}
                        isCollapsed={isSidebarCollapsed}
                        onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
                    />

                    <>
                        {/* Pane 2: Thread list or Calendar */}
                        <div
                            className={`border-r flex flex-col bg-background min-w-0 ${selectedThread ? 'hidden lg:flex' : 'flex'}`}
                            style={selectedFolder === 'calendar' ? { width: calendarWidth, minWidth: 240, maxWidth: 560, flexShrink: 0 } : { flex: 1, maxWidth: '400px' }}
                        >
                            {selectedFolder === 'calendar' ? (
                                <ConversationsCalendar ref={calendarRef} onSelectThread={handleSelectThreadById} t={t} />
                            ) : (
                                <>
                                    {/* Search bar + sync (mobile sync is here since sidebar is hidden) */}
                                    <div className="p-3 border-b shrink-0">
                                        <div className="flex gap-2">
                                            <div className="relative flex-1">
                                                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                                                <Input
                                                    placeholder={t('Search threads...')}
                                                    className="pl-8 bg-muted/50 border-none h-8 text-sm focus-visible:ring-1"
                                                    value={searchQuery}
                                                    onChange={(e) => setSearchQuery(e.target.value)}
                                                    onKeyDown={(e) => { if (e.key === 'Enter') fetchThreads(); }}
                                                />
                                            </div>
                                            <Button variant="ghost" size="icon" className="xl:hidden h-8 w-8 shrink-0" onClick={handleSync} disabled={isSyncing}>
                                                <RefreshCw className={`h-3.5 w-3.5 ${isSyncing ? 'animate-spin text-primary' : ''}`} />
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Thread list scroll area */}
                                    <ScrollArea className="flex-1 min-h-0 overflow-y-auto [&_[data-radix-scroll-area-viewport]>div]:!block">
                                        {!gmailAccount ? (
                                            /* No Gmail account */
                                            <div className="flex flex-col items-center justify-center text-center px-4 py-10">
                                                <div className="h-12 w-12 bg-primary/10 rounded-full flex items-center justify-center mb-3">
                                                    <AlertCircle className="h-6 w-6 text-primary" />
                                                </div>
                                                <h3 className="text-sm font-semibold mb-1">{t('Email Not Connected')}</h3>
                                                <p className="text-xs text-muted-foreground mb-4 max-w-xs">
                                                    {isOwner
                                                        ? t('Connect your Gmail account in settings to start managing conversations.')
                                                        : t('Please ask your Company Owner to connect a Gmail account in settings.')}
                                                </p>
                                                {isOwner && (
                                                    <Button size="sm" onClick={() => window.location.href = route('settings', ['#integrations-settings'])}>
                                                        {t('Connect Gmail')}
                                                    </Button>
                                                )}
                                            </div>
                                        ) : gmailAccount?.sync_status === 'error' && threads.length === 0 ? (
                                            /* Sync error */
                                            <div className="flex flex-col items-center justify-center text-center px-4 py-10">
                                                <AlertCircle className="h-10 w-10 text-destructive mb-3" />
                                                <h3 className="text-sm font-semibold mb-1">{t('Synchronization Error')}</h3>
                                                <p className="text-xs text-muted-foreground mb-4 max-w-xs">
                                                    {gmailAccount.sync_error || t('An error occurred while syncing.')}
                                                </p>
                                                <Button variant="outline" size="sm" onClick={handleSync} disabled={isSyncing}>
                                                    <RefreshCw className={`h-3.5 w-3.5 mr-1.5 ${isSyncing ? 'animate-spin' : ''}`} />
                                                    {t('Try Again')}
                                                </Button>
                                            </div>
                                        ) : threads.length > 0 ? (
                                            /* Thread list */
                                            <div className="divide-y">
                                                {threads.map((thread) => (
                                                    <button
                                                        key={thread.id}
                                                        onClick={() => handleSelectThread(thread)}
                                                        className={`w-full text-left py-3 pl-3 pr-5 lg:pr-4 hover:bg-muted/50 transition-colors flex items-start gap-2.5 overflow-hidden min-w-0 ${selectedThread?.id === thread.id ? 'bg-primary/5 border-l-2 border-primary' : ''
                                                            }`}
                                                    >
                                                        <Avatar className="h-8 w-8 shrink-0 border border-primary/10">
                                                            <AvatarFallback className="bg-primary/5 text-primary text-xs font-bold">
                                                                {(thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0])?.charAt(0).toUpperCase() || 'U'}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex justify-between items-center gap-2 mb-0.5 overflow-hidden">
                                                                <div className="flex flex-col min-w-0 flex-1">
                                                                    <span className={`text-sm truncate ${!thread.is_read ? 'font-extrabold' : 'font-semibold'
                                                                        } ${selectedThread?.id === thread.id ? 'text-primary' : 'text-foreground'}`}>
                                                                        {thread.leads?.[0]?.name || thread.contacts?.[0]?.name || thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0] || 'Unknown'}
                                                                    </span>
                                                                    {(thread.leads?.[0] || thread.contacts?.[0]) && (
                                                                        <span className="text-xs text-muted-foreground truncate opacity-70">
                                                                            {thread.leads?.[0]?.email || thread.contacts?.[0]?.email}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <span className="text-xs text-muted-foreground/80 truncate shrink-0 max-w-[80px] text-right">
                                                                    {timeAgoShort(thread.last_message_at)}
                                                                </span>
                                                            </div>
                                                            <div className={`text-sm truncate mb-0.5 ${!thread.is_read ? 'font-bold text-foreground' : 'text-foreground/80'}`}>
                                                                {thread.subject || t('(No Subject)')}
                                                            </div>
                                                            <div className="text-sm text-muted-foreground/70 truncate">
                                                                {thread.snippet}
                                                            </div>
                                                            <div className="mt-1.5 flex flex-wrap gap-1 items-center">
                                                                {(thread.leads?.length > 0 || thread.contacts?.length > 0) && (
                                                                    <>
                                                                        {thread.leads?.length > 0 && (
                                                                            <Badge variant="outline" className="text-[9px] bg-blue-50/50 text-blue-700 border-blue-100 font-bold px-1 py-0">
                                                                                {t('Lead')}
                                                                            </Badge>
                                                                        )}
                                                                        {thread.contacts?.length > 0 && (
                                                                            <Badge variant="outline" className="text-[9px] bg-green-50/50 text-green-700 border-green-100 font-bold px-1 py-0">
                                                                                {t('Contact')}
                                                                            </Badge>
                                                                        )}
                                                                    </>
                                                                )}

                                                                {thread.priority && (
                                                                    <Badge variant="outline" className={`text-[9px] font-bold px-1 py-0 ${thread.priority === 'High' ? 'bg-destructive/10 text-destructive border-destructive/20' :
                                                                        thread.priority === 'Medium' ? 'bg-amber-100 text-amber-700 border-amber-200' :
                                                                            'bg-blue-50 text-blue-600 border-blue-100'
                                                                        }`}>
                                                                        {t(thread.priority)}
                                                                    </Badge>
                                                                )}

                                                                {thread.assignments?.length > 0 && (
                                                                    <div className="flex items-center gap-0.5 ml-auto">
                                                                        <div className="flex -space-x-1.5 overflow-hidden">
                                                                            {thread.assignments.slice(0, 2).map((a: any) => (
                                                                                <Avatar key={a.id} className="h-4 w-4 border-background border">
                                                                                    <AvatarFallback className="text-[6px] bg-muted">{a.name.charAt(0)}</AvatarFallback>
                                                                                </Avatar>
                                                                            ))}
                                                                        </div>
                                                                        {thread.assignments.length > 2 && (
                                                                            <span className="text-[8px] text-muted-foreground font-bold">+{thread.assignments.length - 2}</span>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </button>
                                                ))}

                                                {/* Sentinel for IntersectionObserver */}
                                                <div ref={threadObserverTarget} className="h-4 w-full" />

                                                {loading && (
                                                    <div className="p-4 flex justify-center items-center gap-2 text-primary/60 animate-pulse bg-muted/5">
                                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                                        <span className="text-xs font-medium">{t('Loading more...')}</span>
                                                    </div>
                                                )}

                                                {!hasMoreThreads && !loading && threads.length > 10 && (
                                                    <div className="p-6 text-center bg-muted/5">
                                                        <p className="text-xs text-muted-foreground uppercase tracking-widest">{t('All threads loaded')}</p>
                                                    </div>
                                                )}
                                            </div>
                                        ) : loading && threads.length === 0 ? (
                                            /* Initial Loading State */
                                            <div className="flex flex-col items-center justify-center py-20 animate-pulse">
                                                <RefreshCw className="h-8 w-8 text-primary/20 animate-spin mb-3" />
                                                <p className="text-sm text-muted-foreground">{t('Loading conversations...')}</p>
                                            </div>
                                        ) : (
                                            /* Empty state */
                                            <div className="flex flex-col items-center justify-center text-center px-4 py-10">
                                                <div className="h-12 w-12 bg-muted/50 rounded-full flex items-center justify-center mb-3">
                                                    <Inbox className="h-6 w-6 text-muted-foreground/30" />
                                                </div>
                                                <h3 className="text-sm font-semibold mb-1">{t('No conversations found')}</h3>
                                                <p className="text-xs text-muted-foreground mb-4 max-w-44">
                                                    {gmailAccount?.sync_status === 'syncing'
                                                        ? t('We are currently syncing your inbox...')
                                                        : t('Try clicking the sync button to fetch your latest emails.')}
                                                </p>
                                                {gmailAccount?.sync_error && (
                                                    <div className="p-2 bg-destructive/5 text-destructive border border-destructive/10 rounded-lg text-[10px] mb-3 max-w-xs">
                                                        <span className="font-bold block mb-0.5">{t('Sync Error')}:</span>
                                                        {gmailAccount.sync_error}
                                                    </div>
                                                )}
                                                <Button variant="outline" size="sm" onClick={handleSync} disabled={isSyncing}>
                                                    <RefreshCw className={`h-3 w-3 mr-1.5 ${isSyncing ? 'animate-spin' : ''}`} />
                                                    {t('Sync Now')}
                                                </Button>
                                            </div>
                                        )}
                                    </ScrollArea>
                                </>
                            )}
                        </div>

                        {/* Drag resize handle — only visible when calendar is open alongside the chat pane */}
                        {selectedFolder === 'calendar' && selectedThread && (
                            <div
                                onMouseDown={handleCalendarResizeMouseDown}
                                className="hidden lg:flex w-1.5 cursor-col-resize shrink-0 items-center justify-center group hover:bg-primary/20 transition-colors z-10 select-none"
                                title="Drag to resize"
                            >
                                <div className="w-0.5 h-8 rounded-full bg-border group-hover:bg-primary/50 transition-colors" />
                            </div>
                        )}

                        {/* Pane 3: Thread detail + reply */}
                        <div className={`
                        flex-1 flex flex-col min-w-0 overflow-hidden bg-muted/5 transition-all duration-300 ease-in-out
                        ${!selectedThread ? 'hidden lg:flex' : 'flex'}
                    `}>
                            {selectedThread ? (
                                <>
                                    {/* Thread header */}
                                    <div className="h-12 lg:h-14 border-b flex items-center justify-between px-3 lg:px-4 bg-background shrink-0">
                                        <div className="min-w-0 flex-1 flex items-center gap-2">
                                            <Button variant="ghost" size="icon" className="lg:hidden h-8 w-8 shrink-0" onClick={handleBack}>
                                                <ArrowLeft className="h-4 w-4" />
                                            </Button>
                                            <div className="min-w-0">
                                                <h2 className="text-base font-semibold truncate">
                                                    {selectedThread.subject || t('(No Subject)')}
                                                </h2>
                                                <div className="text-sm text-muted-foreground flex items-center gap-1.5">
                                                    <span className="truncate max-w-xs">{selectedThread.participants?.join(', ')}</span>
                                                    <span>·</span>
                                                    <span className="shrink-0">{selectedThread.message_count} {t('messages')}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1 ml-2 shrink-0">
                                            {/* Status Picker */}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="outline" size="sm" className="h-8 text-xs font-bold gap-1.5 px-2.5">
                                                        <Badge className={`w-2 h-2 rounded-full p-0 ${selectedThread.status === 'Closed' ? 'bg-gray-400' : 'bg-green-500'}`} />
                                                        {selectedThread.status || t('Open')}
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-32">
                                                    <DropdownMenuItem onClick={() => handleUpdateMetadata({ status: 'Open' })}>
                                                        <Badge className="w-2 h-2 rounded-full p-0 bg-green-500 mr-2" />
                                                        {t('Open')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => handleUpdateMetadata({ status: 'Closed' })}>
                                                        <Badge className="w-2 h-2 rounded-full p-0 bg-gray-400 mr-2" />
                                                        {t('Closed')}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {selectedThread.status === 'Archive' ? (
                                                        <DropdownMenuItem onClick={() => handleUpdateMetadata({ status: 'Open' })} className="text-primary focus:text-primary">
                                                            <Inbox className="w-3.5 h-3.5 mr-2" />
                                                            {t('Restore to Inbox')}
                                                        </DropdownMenuItem>
                                                    ) : (
                                                        <DropdownMenuItem onClick={() => handleUpdateMetadata({ status: 'Archive' })} className="text-muted-foreground">
                                                            <Archive className="w-3.5 h-3.5 mr-2" />
                                                            {t('Archive')}
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>

                                            {/* Follow-up Trigger */}
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="h-8 w-8 text-primary border-primary/20 hover:bg-primary/5"
                                                onClick={() => setShowFollowUpModal(true)}
                                                title={t('Auto Follow-ups')}
                                            >
                                                <Clock className="h-4 w-4" />
                                            </Button>

                                            {/* Assignment Picker */}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="outline" size="icon" className="h-8 w-8 relative">
                                                        <UserCheck className="h-4 w-4" />
                                                        {selectedThread.assignments?.length > 0 && (
                                                            <span className="absolute -top-1 -right-1 bg-primary text-primary-foreground text-[8px] font-bold w-3.5 h-3.5 rounded-full flex items-center justify-center">
                                                                {selectedThread.assignments.length}
                                                            </span>
                                                        )}
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-56 p-0 overflow-hidden">
                                                    <div className="p-2 border-b bg-muted/30">
                                                        <p className="text-[10px] font-bold text-muted-foreground uppercase">{t('Assign Staff')}</p>
                                                    </div>
                                                    <ScrollArea className="h-48">
                                                        <div className="p-1">
                                                            {companyUsers.map((u: any) => (
                                                                <DropdownMenuCheckboxItem
                                                                    key={u.id}
                                                                    onSelect={(e) => e.preventDefault()}
                                                                    checked={selectedThread.assignments?.some((a: any) => a.id === u.id)}
                                                                    onCheckedChange={(checked) => {
                                                                        const currentIds = selectedThread.assignments?.map((a: any) => a.id) || [];
                                                                        const nextIds = checked
                                                                            ? [...currentIds, u.id]
                                                                            : currentIds.filter((id: number) => id !== u.id);
                                                                        handleAssignUsers(nextIds);
                                                                    }}
                                                                    className="flex items-center gap-2 text-xs py-2"
                                                                >
                                                                    <Avatar className="h-5 w-5">
                                                                        <AvatarFallback className="text-[8px]">{u.name.charAt(0)}</AvatarFallback>
                                                                    </Avatar>
                                                                    {u.name}
                                                                </DropdownMenuCheckboxItem>
                                                            ))}
                                                        </div>
                                                    </ScrollArea>
                                                </DropdownMenuContent>
                                            </DropdownMenu>

                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8">
                                                        <MoreVertical className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-48">
                                                    <DropdownMenuLabel className="text-xs uppercase text-muted-foreground">{t('Thread Priority')}</DropdownMenuLabel>
                                                    {['Low', 'Medium', 'High'].map(p => (
                                                        <DropdownMenuItem key={p} onClick={() => handleUpdateMetadata({ priority: p })}>
                                                            <div className={`w-2.5 h-2.5 rounded-full mr-2 ${p === 'High' ? 'bg-destructive' : p === 'Medium' ? 'bg-amber-500' : 'bg-blue-500'}`} />
                                                            {t(p)}
                                                            {selectedThread.priority === p && <CheckCircle className="ml-auto h-3 w-3 text-primary" />}
                                                        </DropdownMenuItem>
                                                    ))}
                                                </DropdownMenuContent>
                                            </DropdownMenu>

                                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setShowContactSidebar(!showContactSidebar)}>
                                                <User className={`h-4 w-4 ${showContactSidebar ? 'text-primary' : ''}`} />
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Messages */}
                                    <ScrollArea className="flex-1 min-h-0">
                                        <div className="flex flex-col-reverse pt-4 lg:pt-6 px-3 lg:px-4 pb-2 lg:pb-3 space-y-reverse space-y-4 lg:space-y-6 max-w-4xl mx-auto">
                                            {/* Scroll to bottom target (Native start) */}
                                            <div ref={messagesEndRef} className="h-0 shrink-0 invisible pointer-events-none" />

                                            {selectedThread.messages?.map((msg: any) => (
                                                <div key={msg.id} className="flex gap-2 lg:gap-3">
                                                    <Avatar className="h-7 w-7 lg:h-8 lg:w-8 shrink-0 border">
                                                        {msg.sender?.avatar ? (
                                                            <AvatarImage src={msg.sender.avatar} />
                                                        ) : null}
                                                        <AvatarFallback className="bg-muted text-xs">
                                                            {(msg.sender?.name || msg.from_email)?.charAt(0).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0 max-w-full space-y-1.5">
                                                        <div className="flex items-start justify-between gap-2 overflow-hidden min-w-0">
                                                            <span className="text-sm font-semibold truncate flex-1 min-w-0">
                                                                {msg.from_name && msg.from_name !== msg.from_email
                                                                    ? `${msg.from_name} <${msg.from_email}>`
                                                                    : msg.from_email}
                                                                {msg.sender && (
                                                                    <span className="ml-1 text-xs font-normal text-muted-foreground italic">
                                                                        sent by {msg.sender.name}
                                                                    </span>
                                                                )}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground truncate shrink-0 max-w-[120px] text-right flex items-center gap-1.5 justify-end">
                                                                {timeAgo(msg.sent_at)}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-6 w-6 rounded-full hover:bg-muted"
                                                                    onClick={() => handleInlineReply(msg)}
                                                                    title={t('Reply to this message')}
                                                                >
                                                                    <CornerDownLeft className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </span>
                                                        </div>
                                                        <div className="bg-background border rounded-lg p-3 shadow-sm text-sm leading-relaxed overflow-hidden break-words w-full [overflow-wrap:anywhere]">
                                                            {msg.body_html ? (
                                                                <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(msg.body_html) }} />
                                                            ) : (
                                                                <span className="whitespace-pre-wrap">{msg.body_preview}</span>
                                                            )}
                                                        </div>
                                                        {/* Local Storage Attachments */}
                                                        {msg.media && msg.media.length > 0 && (
                                                            <div className="flex flex-wrap gap-2 mt-2">
                                                                {msg.media.map((file: any) => {
                                                                    const isImage = file.mime_type?.startsWith('image/');
                                                                    const thumbUrl = file.generated_conversions?.thumb
                                                                        ? (file.original_url?.replace(/\/[^\/]+$/, '/conversions/' + file.name + '-thumb.' + file.file_name?.split('.').pop()))
                                                                        : null;
                                                                    return isImage ? (
                                                                        <a key={file.id} href={file.original_url} target="_blank" rel="noopener noreferrer" className="block">
                                                                            <img
                                                                                src={thumbUrl || file.original_url}
                                                                                alt={file.name}
                                                                                className="max-w-44 max-h-[140px] rounded-md border object-cover hover:opacity-80 transition-opacity"
                                                                            />
                                                                        </a>
                                                                    ) : (
                                                                        <a
                                                                            key={file.id}
                                                                            href={file.original_url}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="flex items-center gap-2 px-3 py-2 border rounded-lg bg-muted/30 hover:bg-muted/60 transition-colors max-w-[220px]"
                                                                        >
                                                                            <FileText className="h-4 w-4 text-primary shrink-0" />
                                                                            <span className="text-xs truncate flex-1 min-w-0">{file.file_name}</span>
                                                                            <Download className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                                                        </a>
                                                                    );
                                                                })}
                                                            </div>
                                                        )}

                                                        {/* Live Proxy Attachments (Gmail Cloud Storage) */}
                                                        {msg.live_attachments && msg.live_attachments.length > 0 && (
                                                            <div className="flex flex-wrap gap-2 mt-2">
                                                                {msg.live_attachments.map((file: any) => (
                                                                    <a
                                                                        key={file.attachment_id}
                                                                        href={`/api/conversations/messages/${msg.id}/attachments/${file.attachment_id}/download?filename=${encodeURIComponent(file.file_name)}`}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="flex items-center gap-2 px-3 py-2 border rounded-lg bg-primary/5 hover:bg-primary/10 transition-colors max-w-[220px] group"
                                                                    >
                                                                        <FileText className="h-4 w-4 text-primary shrink-0" />
                                                                        <span className="text-xs truncate flex-1 min-w-0 font-medium group-hover:underline">{file.file_name}</span>
                                                                        <Download className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                                                    </a>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}

                                            {loadingMoreMessages && (
                                                <div className="flex justify-center items-center py-2 text-muted-foreground text-xs italic">
                                                    <RefreshCw className="h-3 w-3 animate-spin mr-2" />
                                                    {t('Loading earlier messages...')}
                                                </div>
                                            )}

                                            {/* Observer target for loading older history (now logically at the end of the reversed list) */}
                                            <div ref={messagesTopObserverTarget} className="h-1 w-full shrink-0" />
                                        </div>
                                    </ScrollArea>

                                    {/* Reply box */}
                                    <div className="border-t bg-background shrink-0 p-2 lg:p-3">
                                        <div className={`max-w-4xl mx-auto border rounded-lg shadow-sm focus-within:ring-1 focus-within:ring-primary/30 overflow-hidden relative ${selectedThread.status === 'Archive' ? 'min-h-[10rem]' : ''}`}>
                                            {selectedThread.status === 'Archive' && (
                                                <div className="absolute inset-0 z-10 bg-background/80 backdrop-blur-[1px] flex items-center justify-center">
                                                    <div className="flex flex-col items-center gap-2 text-center p-4">
                                                        <div className="p-2 rounded-full bg-amber-50 text-amber-600">
                                                            <Archive className="w-5 h-5" />
                                                        </div>
                                                        <p className="text-sm font-medium text-foreground">
                                                            {t('This thread is archived')}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground max-w-60">
                                                            {t('Restore it to inbox to reply or send messages.')}
                                                        </p>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="mt-1 h-8"
                                                            onClick={() => handleUpdateMetadata({ status: 'Open' })}
                                                        >
                                                            <Inbox className="w-3.5 h-3.5 mr-2" />
                                                            {t('Restore to Inbox')}
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                            {/* Staff assignment/permission check overlay */}
                                            {selectedThread.status !== 'Archive' && !isOwner && (!hasPermission(permissions, 'reply-conversations') || (isStaff && !selectedThread.assignments?.some((a: any) => a.id === auth?.user?.id))) && (
                                                <div className="absolute inset-0 z-10 bg-background/80 backdrop-blur-[1px] flex items-center justify-center min-h-[10rem]">
                                                    <div className="flex flex-col items-center gap-2 text-center p-4">
                                                        <div className="p-2 rounded-full bg-amber-50 text-amber-600">
                                                            <AlertCircle className="w-5 h-5" />
                                                        </div>
                                                        <p className="text-sm font-medium text-foreground">
                                                            {!hasPermission(permissions, 'reply-conversations')
                                                                ? t('You do not have permission to reply')
                                                                : t('You are not assigned to this thread')}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground max-w-60">
                                                            {!hasPermission(permissions, 'reply-conversations')
                                                                ? t('Contact your administrator to request reply access.')
                                                                : t('Ask a manager to assign you to this thread to reply.')}
                                                        </p>
                                                    </div>
                                                </div>
                                            )}

                                            {activeReplyMessage && (
                                                <div className="flex items-center justify-between px-3 py-2 bg-muted/30 border-b">
                                                    <span className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                                                        <CornerDownLeft className="h-3.5 w-3.5" />
                                                        {t('Replying to:')} <strong className="text-foreground">{activeReplyMessage.from_name || activeReplyMessage.from_email}</strong>
                                                    </span>
                                                    <Button variant="ghost" size="sm" className="h-6 w-6 p-0 rounded-full" onClick={() => setActiveReplyMessage(null)}>
                                                        <X className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            )}

                                            <div
                                                className="w-full min-h-[3.75rem] lg:min-h-[5rem] cursor-text bg-background"
                                                onClick={() => replyEditor?.commands.focus()}
                                            >
                                                <EditorContent editor={replyEditor} />
                                            </div>
                                            {showReplyCcBcc && (
                                                <div className="border-t bg-muted/5 animate-in fade-in slide-in-from-top-1 duration-200">
                                                    <div className="flex px-2.5 py-1.5 border-b group">
                                                        <span className="w-10 text-[10px] font-bold text-muted-foreground pt-1.5">CC</span>
                                                        <div className="flex flex-wrap gap-1.5 flex-1 items-center">
                                                            {Array.from(new Set([
                                                                ...(selectedThread?.participants || []).map((p: string) => {
                                                                    const emailMatch = p.match(/<([^>]+)>/);
                                                                    return emailMatch ? emailMatch[1].toLowerCase() : p.toLowerCase();
                                                                }),
                                                                ...replyCcList
                                                            ].filter((rawEmail: string) => {
                                                                const accountEmail = (gmailAccount?.email || '').toLowerCase();
                                                                const primaryToRaw = activeReplyMessage ? (activeReplyMessage.from_email || '').toLowerCase() : '';
                                                                return rawEmail.trim() !== accountEmail.trim() && rawEmail.trim() !== primaryToRaw.trim();
                                                            }))).map((ccEmail) => {
                                                                const isSelected = replyCcList.includes(ccEmail);
                                                                return (
                                                                    <Badge
                                                                        key={ccEmail}
                                                                        variant={isSelected ? "default" : "outline"}
                                                                        className="cursor-pointer text-xs px-2 py-0.5"
                                                                        onClick={() => {
                                                                            setReplyCcList(prev =>
                                                                                prev.includes(ccEmail)
                                                                                    ? prev.filter(e => e !== ccEmail)
                                                                                    : [...prev, ccEmail]
                                                                            );
                                                                        }}
                                                                    >
                                                                        {ccEmail}
                                                                        {isSelected && <Check className="h-3 w-3 ml-1" />}
                                                                    </Badge>
                                                                );
                                                            })}
                                                            <input
                                                                type="email"
                                                                placeholder={t('Add CC...')}
                                                                value={ccInput}
                                                                onChange={(e) => setCcInput(e.target.value)}
                                                                onKeyDown={(e) => {
                                                                    if (e.key === 'Enter' || e.key === ',' || e.key === ' ') {
                                                                        e.preventDefault();
                                                                        const val = ccInput.trim().replace(/,/g, '');
                                                                        if (val && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                                                                            setReplyCcList(prev => prev.includes(val) ? prev : [...prev, val]);
                                                                            setCcInput('');
                                                                        }
                                                                    }
                                                                }}
                                                                className="flex-1 min-w-0 bg-transparent border-none text-xs focus:ring-0 p-0 placeholder:text-muted-foreground/50 h-5 outline-none"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center px-2.5 py-1.5 group">
                                                        <span className="w-10 text-[10px] font-bold text-muted-foreground group-focus-within:text-foreground">BCC</span>
                                                        <div className="flex flex-wrap gap-1.5 flex-1 items-center">
                                                            {Array.from(new Set([
                                                                ...(selectedThread?.participants || []).map((p: string) => {
                                                                    const emailMatch = p.match(/<([^>]+)>/);
                                                                    return emailMatch ? emailMatch[1].toLowerCase() : p.toLowerCase();
                                                                }),
                                                                ...replyBccList
                                                            ].filter((rawEmail: string) => {
                                                                const accountEmail = (gmailAccount?.email || '').toLowerCase();
                                                                const primaryToRaw = activeReplyMessage ? (activeReplyMessage.from_email || '').toLowerCase() : '';
                                                                return rawEmail.trim() !== accountEmail.trim() && rawEmail.trim() !== primaryToRaw.trim();
                                                            }))).map((bccEmail) => {
                                                                const isSelected = replyBccList.includes(bccEmail);
                                                                return (
                                                                    <Badge
                                                                        key={bccEmail}
                                                                        variant={isSelected ? "default" : "outline"}
                                                                        className="cursor-pointer text-xs px-2 py-0.5"
                                                                        onClick={() => {
                                                                            setReplyBccList(prev =>
                                                                                prev.includes(bccEmail)
                                                                                    ? prev.filter(e => e !== bccEmail)
                                                                                    : [...prev, bccEmail]
                                                                            );
                                                                        }}
                                                                    >
                                                                        {bccEmail}
                                                                        {isSelected && <Check className="h-3 w-3 ml-1" />}
                                                                    </Badge>
                                                                );
                                                            })}
                                                            <input
                                                                type="email"
                                                                placeholder={t('Add BCC...')}
                                                                value={bccInput}
                                                                onChange={(e) => setBccInput(e.target.value)}
                                                                onKeyDown={(e) => {
                                                                    if (e.key === 'Enter' || e.key === ',' || e.key === ' ') {
                                                                        e.preventDefault();
                                                                        const val = bccInput.trim().replace(/,/g, '');
                                                                        if (val && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                                                                            setReplyBccList(prev => prev.includes(val) ? prev : [...prev, val]);
                                                                            setBccInput('');
                                                                        }
                                                                    }
                                                                }}
                                                                className="flex-1 min-w-0 bg-transparent border-none text-xs focus:ring-0 p-0 placeholder:text-muted-foreground/50 h-5 outline-none"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                            {/* Reply attachment preview */}
                                            {replyFiles.length > 0 && (
                                                <div className="flex flex-wrap gap-2 px-2.5 py-2 border-t bg-muted/10">
                                                    {replyFiles.map((file, idx) => (
                                                        <div key={idx} className="flex items-center gap-1.5 bg-background border rounded-md px-2 py-1 text-xs">
                                                            <Paperclip className="h-3 w-3 text-muted-foreground" />
                                                            <span className="truncate max-w-[120px]">{file.name}</span>
                                                            <button onClick={() => setReplyFiles(prev => prev.filter((_, i) => i !== idx))} className="text-muted-foreground hover:text-destructive" disabled={selectedThread.status === 'Archive'}>
                                                                <X className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            {/* Reply Formatting Toolbar */}
                                            {showReplyFormatting && replyEditor && (
                                                <div className="flex items-center gap-0.5 px-2.5 py-1 border-t bg-muted/5 animate-in slide-in-from-bottom-1 duration-200">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className={cn("h-7 w-7 p-0", replyEditor.isActive('bold') && "bg-muted text-primary")}
                                                        onClick={() => replyEditor.chain().focus().toggleBold().run()}
                                                        disabled={selectedThread.status === 'Archive'}
                                                    >
                                                        <Bold className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className={cn("h-7 w-7 p-0", replyEditor.isActive('italic') && "bg-muted text-primary")}
                                                        onClick={() => replyEditor.chain().focus().toggleItalic().run()}
                                                        disabled={selectedThread.status === 'Archive'}
                                                    >
                                                        <Italic className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <div className="w-px h-3 bg-border mx-1" />
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                                        onClick={() => replyEditor.chain().focus().unsetAllMarks().run()}
                                                        disabled={selectedThread.status === 'Archive'}
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            )}
                                            <div className="flex items-center justify-between px-2.5 py-1.5 bg-muted/20 border-t">
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className={cn("h-7 w-7 transition-colors rounded-full", showReplyFormatting ? "text-primary bg-primary/10" : "text-muted-foreground hover:text-foreground hover:bg-muted/50")}
                                                        onClick={() => setShowReplyFormatting(!showReplyFormatting)}
                                                        disabled={submittingReply || selectedThread.status === 'Archive'}
                                                    >
                                                        <Type className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className={cn("h-7 w-7 transition-colors rounded-full", showReplyCcBcc ? "text-primary bg-primary/10" : "text-muted-foreground hover:text-foreground hover:bg-muted/50")}
                                                        onClick={() => setShowReplyCcBcc(!showReplyCcBcc)}
                                                        disabled={submittingReply || selectedThread.status === 'Archive'}
                                                    >
                                                        <UserPlus className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <div className="w-px h-4 bg-border mx-1" />
                                                    <input type="file" multiple ref={replyFileRef} className="hidden" onChange={(e) => { if (e.target.files) setReplyFiles(prev => [...prev, ...Array.from(e.target.files!)]); e.target.value = ''; }} />
                                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => replyFileRef.current?.click()} disabled={submittingReply || selectedThread.status === 'Archive'}>
                                                        <Paperclip className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <EmojiPicker
                                                        disabled={submittingReply || selectedThread.status === 'Archive'}
                                                        onSelect={(emoji) => replyEditor?.chain().focus().insertContent(emoji).run()}
                                                    />
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        size="sm"
                                                        className="gap-1.5 px-4 h-7 text-xs"
                                                        onClick={handleSendReply}
                                                        disabled={submittingReply || !replyBody.trim() || selectedThread.status === 'Archive'}
                                                    >
                                                        {submittingReply ? (
                                                            <RefreshCw className="h-3 w-3 animate-spin" />
                                                        ) : (
                                                            <Send className="h-3 w-3" />
                                                        )}
                                                        {submittingReply ? t('Sending...') : t('Send Reply')}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                /* No thread selected (visible on md+) */
                                <div className="flex-1 flex flex-col items-center justify-center text-center p-8">
                                    <div className="h-16 w-16 bg-muted/50 rounded-full flex items-center justify-center mb-4">
                                        <Inbox className="h-8 w-8 text-muted-foreground/30" />
                                    </div>
                                    <h3 className="text-lg font-semibold mb-1">{t('Select a conversation')}</h3>
                                    <p className="text-sm text-muted-foreground max-w-xs">
                                        {t('Choose a thread from the list to view the full message history and CRM details.')}
                                    </p>
                                </div>
                            )}
                        </div>
                    </>
                    {/* Pane 4: CRM Context Sidebar */}
                    {selectedThread && (
                        <>
                            {/* Backdrop for mobile */}
                            <div
                                className={cn(
                                    "absolute inset-0 z-20 bg-black/20 lg:bg-transparent xl:hidden transition-opacity duration-300 ease-in-out",
                                    showContactSidebar ? "opacity-100 pointer-events-auto" : "opacity-0 pointer-events-none"
                                )}
                                onClick={() => setShowContactSidebar(false)}
                            />
                            <div
                                className={cn(
                                    "absolute right-0 top-0 bottom-0 z-30 w-[300px] min-w-0 min-h-0 max-w-[88vw] border-l flex flex-col bg-background shadow-2xl overflow-hidden",
                                    "transition-all duration-300 ease-in-out",
                                    "xl:relative xl:z-10 xl:max-w-none xl:shadow-none xl:h-[calc(100vh-116px)] xl:self-stretch xl:min-h-0",
                                    showContactSidebar 
                                        ? "translate-x-0 xl:flex-shrink-0 xl:w-[300px] opacity-100" 
                                        : "translate-x-full xl:translate-x-0 xl:flex-shrink xl:w-0 opacity-0 xl:opacity-100 xl:border-l-0"
                                )}
                            >

                            {/* ── Sidebar Header ────────────────────────── */}
                            <div className="flex items-center justify-between gap-2 min-w-0 px-4 py-3 border-b shrink-0">
                                <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                                    {(() => {
                                        const externalParticipant = selectedThread.participants?.find((p: string) => p !== gmailAccount?.email) || selectedThread.participants?.[0];
                                        const contactName = selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || externalParticipant || 'Contact';
                                        return (
                                            <>
                                                <Avatar className="h-7 w-7 border shrink-0">
                                                    <AvatarFallback className="text-[11px] font-bold bg-primary/10 text-primary">
                                                        {String(contactName).charAt(0).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold truncate leading-tight">{contactName}</p>
                                                    <p className="text-xs text-muted-foreground truncate leading-tight">
                                                        {selectedThread.leads?.[0]?.company || selectedThread.contacts?.[0]?.account?.name || ''}
                                                    </p>
                                                </div>
                                            </>
                                        );
                                    })()}
                                </div>
                                <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0" onClick={() => setShowContactSidebar(false)}>
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>

                            {/* ── Section Tabs ──────────────────────────── */}
                            <div className="flex border-b shrink-0">
                                {[
                                    { key: 'lead' as const, label: t('Lead'), icon: User },
                                    { key: 'opportunities' as const, label: t('Opportunity'), icon: Briefcase },
                                ].map(tab => (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveSidebarSection(tab.key)}
                                        className={`flex-1 flex flex-col items-center gap-1.5 py-3 text-xs font-bold uppercase tracking-wider transition-all border-b-2 ${activeSidebarSection === tab.key
                                                ? 'border-primary text-primary bg-primary/5'
                                                : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/40'
                                            }`}
                                    >
                                        <tab.icon className="h-4 w-4" />
                                        {tab.label}
                                    </button>
                                ))}
                            </div>

                            {/* ── Section Body ──────────────────────────── */}
                            <ScrollArea className="flex-1 min-h-0 transition-all duration-300 ease-in-out">

                                {/* ═══════════════ LEAD SECTION ═══════════════ */}
                                {activeSidebarSection === 'lead' && (
                                    <div className="p-4 space-y-4 transition-all duration-300 ease-in-out">
                                        {selectedThread.leads?.length > 0 ? (
                                            <>
                                                {selectedThread.leads.map((lead: any) => {
                                                    const currentStatus = localLeadStatuses[lead.id] ?? lead.lead_status?.name ?? lead.leadStatus?.name ?? leadStatuses[0]?.name ?? '';
                                                    const statusConfig: Record<string, { color: string; dot: string }> = {
                                                        'New': { color: 'text-blue-700 bg-blue-50 border-blue-200', dot: 'bg-blue-500' },
                                                        'Contacted': { color: 'text-violet-700 bg-violet-50 border-violet-200', dot: 'bg-violet-500' },
                                                        'Qualified': { color: 'text-emerald-700 bg-emerald-50 border-emerald-200', dot: 'bg-emerald-500' },
                                                        'Unqualified': { color: 'text-rose-700 bg-rose-50 border-rose-200', dot: 'bg-rose-400' },
                                                    };
                                                    const sc = statusConfig[currentStatus] ?? { color: 'text-muted-foreground bg-muted border-border', dot: 'bg-muted-foreground' };
                                                    return (
                                                        <div key={lead.id} className="rounded-xl border bg-card shadow-sm overflow-hidden">
                                                            {/* Lead card header */}
                                                            <div className="flex items-center justify-between gap-2 min-w-0 px-3 py-2.5 bg-muted/30 border-b">
                                                                <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                                                                    <div className={`h-2 w-2 rounded-full shrink-0 ${sc.dot}`} />
                                                                    <span className="min-w-0 flex-1 truncate text-sm font-semibold">{lead.name}</span>
                                                                </div>
                                                                <Badge variant="outline" className="text-[9px] px-1.5 py-0 shrink-0 ml-1">
                                                                    {t('LEAD')}
                                                                </Badge>
                                                            </div>

                                                            {/* Lead detail rows */}
                                                            <div className="px-3 py-2.5 space-y-4 text-sm">
                                                                <div className="flex min-w-0 items-center justify-between gap-2">
                                                                    <span className="shrink-0 text-muted-foreground">{t('Company')}</span>
                                                                    <span className="min-w-0 flex-1 truncate text-right font-medium">{lead.company || '—'}</span>
                                                                </div>
                                                                <div className="flex min-w-0 items-center justify-between gap-2">
                                                                    <span className="shrink-0 text-muted-foreground">{t('Value')}</span>
                                                                    <span className="min-w-0 flex-1 text-right font-semibold text-emerald-600">
                                                                        {lead.value ? `$${Number(lead.value).toLocaleString()}` : '—'}
                                                                    </span>
                                                                </div>
                                                                {lead.assigned_to_user && (
                                                                    <div className="flex min-w-0 items-center justify-between gap-2">
                                                                        <span className="shrink-0 text-muted-foreground">{t('Owner')}</span>
                                                                        <span className="min-w-0 flex-1 truncate text-right font-medium">{lead.assigned_to_user.name}</span>
                                                                    </div>
                                                                )}

                                                                {/* Inline Status Selector */}
                                                                <div className="pt-1">
                                                                    <p className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">
                                                                        {t('Status')}
                                                                    </p>
                                                                    {canEditLeadStatus && leadStatuses.length > 0 ? (
                                                                        <Select
                                                                            value={currentStatus}
                                                                            onValueChange={(val) => persistLeadStatus(lead, val)}
                                                                            disabled={savingLeadId === lead.id}
                                                                        >
                                                                            <SelectTrigger className={`h-7 text-xs font-semibold border rounded-md px-2.5 w-full ${sc.color}`}>
                                                                                <SelectValue placeholder={t('Status')} />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                {leadStatuses.map((ls: any) => (
                                                                                    <SelectItem key={ls.id} value={ls.name} className="text-xs">{ls.name}</SelectItem>
                                                                                ))}
                                                                            </SelectContent>
                                                                        </Select>
                                                                    ) : (
                                                                        <span className={`inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-1 rounded-md border ${sc.color}`}>
                                                                            <span className={`h-1.5 w-1.5 rounded-full ${sc.dot}`} />
                                                                            {currentStatus}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {/* Lead card footer */}
                                                            <div className="px-3 py-2 border-t bg-muted/10 flex items-center justify-between">
                                                                <span className="text-[10px] text-muted-foreground">
                                                                    {timeAgo(lead.created_at)}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    );
                                                })}

                                                {/* Global lead activity stream preview (same source as Lead Detail page) */}
                                                <div className="mt-6 pt-6 border-t space-y-4">
                                                    <div>
                                                        <h4 className="text-[10px] font-bold text-muted-foreground uppercase tracking-widest mb-3 flex items-center justify-between">
                                                            {t('Recent Lead Activity')}
                                                            <Clock className="h-3 w-3 opacity-50" />
                                                        </h4>
                                                        <div className="space-y-4 relative">
                                                            <div className="absolute left-1.5 top-1 bottom-1 w-px bg-border/60" />
                                                            {(() => {
                                                                const preview = selectedThread.leads?.[0]?.recent_stream_preview ?? [];
                                                                if (preview.length === 0) {
                                                                    return (
                                                                        <p className="text-[10px] text-muted-foreground pl-6">{t('No activities yet')}</p>
                                                                    );
                                                                }
                                                                return preview.map((activity: any, idx: number) => {
                                                                    const dot = getLeadStreamPreviewDotClass(activity);
                                                                    const rawDesc = activity.description;
                                                                    const descIsHtml =
                                                                        typeof rawDesc === 'string' && /<[^>]+>/.test(rawDesc);
                                                                    return (
                                                                        <div key={String(activity.id ?? idx)} className="flex gap-4 group">
                                                                            <div className="relative shrink-0 mt-1">
                                                                                <div className={`h-3 w-3 rounded-full ring-4 shadow-sm ${dot}`} />
                                                                            </div>
                                                                            <div className="min-w-0 pb-1">
                                                                                <p className="text-[11px] font-bold leading-tight group-hover:text-primary transition-colors line-clamp-2 break-words">
                                                                                    {activity.title}
                                                                                </p>
                                                                                {rawDesc ? (
                                                                                    descIsHtml ? (
                                                                                        <div
                                                                                            className="text-[10px] text-muted-foreground mt-1 line-clamp-2 [&_p]:my-0 [&_*]:text-[10px]"
                                                                                            dangerouslySetInnerHTML={{
                                                                                                __html: sanitizeHtml(String(rawDesc)),
                                                                                            }}
                                                                                        />
                                                                                    ) : (
                                                                                        <p className="text-[10px] text-muted-foreground mt-1 line-clamp-2">
                                                                                            {rawDesc}
                                                                                        </p>
                                                                                    )
                                                                                ) : null}
                                                                                <p className="text-[10px] font-semibold text-muted-foreground/60 mt-2 flex items-center gap-1">
                                                                                    <Clock className="h-2.5 w-2.5" />
                                                                                    {activity.created_at
                                                                                        ? timeAgo(activity.created_at)
                                                                                        : '—'}
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                });
                                                            })()}
                                                        </div>
                                                    </div>

                                                    <a
                                                        href={route('leads.show', selectedThread.leads[0].id)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-primary/20 bg-primary/5 text-primary hover:bg-primary/10 transition-all group"
                                                    >
                                                        <span className="text-[11px] font-bold tracking-tight">{t('Open Full History Stream')}</span>
                                                        <ExternalLink className="h-3.5 w-3.5 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform" />
                                                    </a>
                                                </div>
                                            </>
                                        ) : (
                                            /* No lead linked */
                                            <div className="flex flex-col items-center py-12 px-6 text-center">
                                                <div className="h-14 w-14 rounded-2xl bg-muted/40 flex items-center justify-center mb-4 transition-transform hover:rotate-6">
                                                    <UserPlus className="h-7 w-7 text-muted-foreground/30" />
                                                </div>
                                                <h5 className="text-sm font-bold text-foreground mb-1.5">{t('No CRM Lead Linked')}</h5>
                                                <p className="text-xs text-muted-foreground mb-6 leading-relaxed">
                                                    {t('Contextual lead tracking and opportunity management are unavailable for this conversation.')}
                                                </p>

                                                {selectedThread.suggested_leads?.length > 0 ? (
                                                    <div className="w-full space-y-3">
                                                        <div className="min-w-0 bg-amber-50/50 border border-amber-200/50 p-3 rounded-xl text-left scale-[0.98] hover:scale-100 transition-transform">
                                                            <p className="text-[10px] text-amber-700 font-bold uppercase tracking-widest mb-1.5 flex items-center gap-1.5">
                                                                <AlertCircle className="h-3 w-3 shrink-0" />
                                                                {t('Suggested Match')}
                                                            </p>
                                                            <p className="text-xs text-amber-900 font-bold leading-none break-words">{selectedThread.suggested_leads[0].name}</p>
                                                            <p className="text-[10px] text-amber-600/80 truncate mt-1 min-w-0">{selectedThread.suggested_leads[0].email}</p>
                                                        </div>
                                                        <Button size="sm" className="w-full text-xs h-9 font-bold shadow-lg shadow-primary/10 transition-all hover:translate-y-[-1px]" onClick={() => handleLinkToLead(selectedThread.suggested_leads[0].id)} disabled={!canManage}>
                                                            {t('Link Match to Thread')}
                                                        </Button>
                                                        <Button size="sm" variant="ghost" className="w-full text-xs h-8 text-muted-foreground font-semibold" onClick={() => handleAddAsLead()} disabled={!canManage}>
                                                            {t('Create New Lead')}
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Button size="sm" variant="outline" className="w-full text-xs h-9 font-bold bg-background shadow-sm transition-all hover:bg-muted" onClick={() => handleAddAsLead()} disabled={!canManage}>
                                                        <UserPlus className="h-4 w-4 mr-2" />
                                                        {t('Quick Create Lead')}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* ═══════════════ OPPORTUNITIES SECTION ═══════════════ */}
                                {activeSidebarSection === 'opportunities' && (
                                    <div className="p-4 space-y-3 transition-all duration-300 ease-in-out">
                                        {(() => {
                                            const opportunities = selectedThread.leads?.flatMap((l: any) => l.opportunities ?? []) ?? [];

                                            const oppStatusConfig: Record<string, { badge: string; dot: string; label: string }> = {
                                                'Open': { badge: 'bg-blue-100 text-blue-700 border-blue-200', dot: 'bg-blue-500', label: 'Open' },
                                                'Negotiation': { badge: 'bg-amber-100 text-amber-700 border-amber-200', dot: 'bg-amber-500', label: 'Negotiation' },
                                                'Won': { badge: 'bg-emerald-100 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500', label: 'Won' },
                                                'Lost': { badge: 'bg-rose-100 text-rose-700 border-rose-200', dot: 'bg-rose-500', label: 'Lost' },
                                            };

                                            if (opportunities.length === 0) {
                                                return (
                                                    <div className="flex flex-col items-center py-8 px-3 text-center">
                                                        <div className="h-10 w-10 rounded-full bg-muted/50 flex items-center justify-center mb-3">
                                                            <Briefcase className="h-5 w-5 text-muted-foreground/40" />
                                                        </div>
                                                        <p className="text-xs font-medium text-foreground mb-1">{t('No opportunities')}</p>
                                                        {selectedThread.leads?.length === 0 ? (
                                                            <p className="text-xs text-muted-foreground">{t('Link a lead first to see related opportunities.')}</p>
                                                        ) : (
                                                            <p className="text-xs text-muted-foreground">{t('No opportunities are linked to this lead yet.')}</p>
                                                        )}
                                                    </div>
                                                );
                                            }

                                            return opportunities.map((opp: any) => {
                                                const stageName =
                                                    localOppStatuses[opp.id] ??
                                                    opp.opportunity_stage?.name ??
                                                    opp.opportunityStage?.name ??
                                                    opportunityStages[0]?.name ??
                                                    '';
                                                const sc = oppStatusConfig[stageName] ?? { badge: 'bg-muted text-muted-foreground border-border', dot: 'bg-muted-foreground', label: stageName };
                                                const isExpanded = expandedOpportunityId === opp.id;
                                                return (
                                                    <div key={opp.id} className="rounded-xl border bg-card shadow-sm overflow-hidden">
                                                        {/* Opportunity row — click to expand */}
                                                        <div className="flex min-w-0 items-center justify-between gap-2 px-3 py-2 hover:bg-muted/40 transition-colors">
                                                            <button
                                                                type="button"
                                                                className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden py-1.5 text-left"
                                                                onClick={() => setExpandedOpportunityId(isExpanded ? null : opp.id)}
                                                            >
                                                                <div className={`h-2 w-2 shrink-0 rounded-full ${sc.dot}`} />
                                                                <span className="min-w-0 flex-1 truncate text-sm font-semibold">{opp.name || opp.title}</span>
                                                            </button>
                                                            <div className="flex shrink-0 items-center gap-1.5 ml-1">
                                                                {canEditOpportunityStage && opportunityStages.length > 0 ? (
                                                                    <Select
                                                                        value={stageName}
                                                                        onValueChange={(val) => persistOpportunityStage(opp, val)}
                                                                        disabled={savingOppId === opp.id}
                                                                    >
                                                                        <SelectTrigger className={`h-6 min-w-0 max-w-[7rem] truncate text-[10px] font-bold border rounded-md px-1.5 ${sc.badge}`}>
                                                                            <SelectValue placeholder={t('Stage')} />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {opportunityStages.map((st: any) => (
                                                                                <SelectItem key={st.id} value={st.name} className="text-xs">{st.name}</SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                ) : (
                                                                    <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded border ${sc.badge}`}>
                                                                        {stageName || '—'}
                                                                    </span>
                                                                )}
                                                                <button
                                                                    onClick={() => setExpandedOpportunityId(isExpanded ? null : opp.id)}
                                                                    className="p-1 hover:bg-muted rounded"
                                                                >
                                                                    {isExpanded ? <ChevronUpIcon className="h-3.5 w-3.5 text-muted-foreground" /> : <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />}
                                                                </button>
                                                            </div>
                                                        </div>

                                                        {/* Expanded detail view */}
                                                        {isExpanded && (
                                                            <div className="border-t bg-muted/10 px-3 py-3 space-y-4 animate-in fade-in slide-in-from-top-1 duration-150">
                                                                <div className="flex items-center justify-between text-xs">
                                                                    <span className="text-muted-foreground flex items-center gap-1">
                                                                        <DollarSign className="h-3 w-3" />{t('Value')}
                                                                    </span>
                                                                    <span className="font-semibold text-emerald-600">
                                                                        {opp.amount ? `$${Number(opp.amount).toLocaleString()}` : '—'}
                                                                    </span>
                                                                </div>
                                                                {opp.close_date && (
                                                                    <div className="flex items-center justify-between text-xs">
                                                                        <span className="text-muted-foreground flex items-center gap-1">
                                                                            <Calendar className="h-3 w-3" />{t('Close Date')}
                                                                        </span>
                                                                        <span className="font-medium">{new Date(opp.close_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                                                    </div>
                                                                )}
                                                                {(() => {
                                                                    const prob =
                                                                        opp.opportunity_stage?.probability ??
                                                                        opp.opportunityStage?.probability ??
                                                                        opp.probability;
                                                                    if (prob === undefined || prob === null) return null;
                                                                    return (
                                                                        <div className="space-y-1">
                                                                            <div className="flex items-center justify-between text-xs">
                                                                                <span className="text-muted-foreground flex items-center gap-1">
                                                                                    <TrendingUp className="h-3 w-3" />{t('Probability')}
                                                                                </span>
                                                                                <span className="font-semibold">{prob}%</span>
                                                                            </div>
                                                                            <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                                                                                <div
                                                                                    className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                                                                    style={{ width: `${Math.min(100, Number(prob))}%` }}
                                                                                />
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                })()}
                                                                {opp.id && (
                                                                    <div className="pt-2">
                                                                        <div className="mb-4 pt-3 border-t">
                                                                            <h5 className="text-[9px] font-bold text-muted-foreground uppercase tracking-widest mb-2.5">
                                                                                {t('Opp Activity')}
                                                                            </h5>
                                                                            <div className="space-y-3 relative">
                                                                                <div className="absolute left-1.5 top-1 bottom-1 w-px bg-border/60" />
                                                                                {(() => {
                                                                                    const preview = opp.recent_stream_preview ?? [];
                                                                                    if (preview.length === 0) {
                                                                                        return (
                                                                                            <p className="text-[9px] text-muted-foreground pl-6">{t('No activities yet')}</p>
                                                                                        );
                                                                                    }
                                                                                    return preview.map((activity: any, idx: number) => {
                                                                                        const dot = getLeadStreamPreviewDotClass(activity);
                                                                                        const rawDesc = activity.description;
                                                                                        const descIsHtml =
                                                                                            typeof rawDesc === 'string' && /<[^>]+>/.test(rawDesc);
                                                                                        return (
                                                                                            <div key={String(activity.id ?? idx)} className="flex gap-3 group pl-0.5">
                                                                                                <div className="relative shrink-0 mt-0.5">
                                                                                                    <div className={`h-2.5 w-2.5 rounded-full ring-2 shadow-sm ${dot}`} />
                                                                                                </div>
                                                                                                <div className="min-w-0 pb-0.5">
                                                                                                    <p className="text-[10px] font-bold leading-tight group-hover:text-primary transition-colors">
                                                                                                        {activity.title}
                                                                                                    </p>
                                                                                                    {rawDesc ? (
                                                                                                        descIsHtml ? (
                                                                                                            <div
                                                                                                                className="text-[9px] text-muted-foreground mt-0.5 line-clamp-2 [&_p]:my-0 [&_*]:text-[9px]"
                                                                                                                dangerouslySetInnerHTML={{
                                                                                                                    __html: sanitizeHtml(String(rawDesc)),
                                                                                                                }}
                                                                                                            />
                                                                                                        ) : (
                                                                                                            <p className="text-[9px] text-muted-foreground mt-0.5 line-clamp-2">
                                                                                                                {rawDesc}
                                                                                                            </p>
                                                                                                        )
                                                                                                    ) : null}
                                                                                                    <p className="text-[9px] font-semibold text-muted-foreground/60 mt-1 flex items-center gap-1">
                                                                                                        <Clock className="h-2 w-2" />
                                                                                                        {activity.created_at ? timeAgo(activity.created_at) : '—'}
                                                                                                    </p>
                                                                                                </div>
                                                                                            </div>
                                                                                        );
                                                                                    });
                                                                                })()}
                                                                            </div>
                                                                        </div>
                                                                        <a href={route('opportunities.show', opp.id)} target="_blank" rel="noopener noreferrer">
                                                                            <Button variant="ghost" size="sm" className="h-8 w-full text-[10px] gap-1 text-primary font-bold hover:bg-primary/10 border border-primary/10 transition-all active:scale-[0.98]">
                                                                                {t('View Detailed Opportunity')}
                                                                                <ExternalLink className="h-3 w-3" />
                                                                            </Button>
                                                                        </a>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            });
                                        })()}
                                    </div>
                                )}

                                {/* स्टैंडअलोन गतिविधि अनुभाग हटा दिया गया (लीड्स और ऑप्स में विलय कर दिया गया) */}
                            </ScrollArea>
                        </div>
                        </>
                    )}
            </div>

            {/* Compose Dialog */}
            <Dialog open={showCompose} onOpenChange={setShowCompose}>
                <DialogContent className="sm:max-w-[700px] p-0 overflow-hidden gap-0 border-none shadow-2xl rounded-xl">
                    <DialogHeader className="px-6 py-4 bg-primary/5 border-b">
                        <DialogTitle className="text-lg font-semibold flex items-center text-primary">
                            <Mail className="w-5 h-5 mr-2" />
                            {t('New Message')}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col bg-background">
                        <div className="flex items-center px-6 py-3 border-b focus-within:bg-muted/30 transition-colors group">
                            <Label htmlFor="compose-to" className="w-16 text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('To')}</Label>
                            <Input
                                id="compose-to"
                                value={composeTo}
                                onChange={(e) => setComposeTo(e.target.value)}
                                placeholder="recipient@example.com"
                                className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto break-all bg-transparent text-sm"
                            />
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowComposeCcBcc(!showComposeCcBcc)}
                                className="h-7 text-[10px] font-bold text-muted-foreground hover:text-primary transition-colors uppercase tracking-tight"
                            >
                                {showComposeCcBcc ? t('Hide CC/BCC') : t('CC/BCC')}
                            </Button>
                        </div>
                        {showComposeCcBcc && (
                            <div className="animate-in fade-in slide-in-from-top-1 duration-200">
                                <div className="flex items-center px-6 py-2.5 border-b bg-muted/5 group">
                                    <Label htmlFor="compose-cc" className="w-16 text-xs font-bold text-muted-foreground group-focus-within:text-foreground">{t('Cc')}</Label>
                                    <Input
                                        id="compose-cc"
                                        value={composeCc}
                                        onChange={(e) => setComposeCc(e.target.value)}
                                        placeholder="cc1@example.com, cc2@example.com"
                                        className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto bg-transparent text-sm"
                                    />
                                </div>
                                <div className="flex items-center px-6 py-2.5 border-b bg-muted/5 group">
                                    <Label htmlFor="compose-bcc" className="w-16 text-xs font-bold text-muted-foreground group-focus-within:text-foreground">{t('Bcc')}</Label>
                                    <Input
                                        id="compose-bcc"
                                        value={composeBcc}
                                        onChange={(e) => setComposeBcc(e.target.value)}
                                        placeholder="bcc1@example.com, bcc2@example.com"
                                        className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto bg-transparent text-sm"
                                    />
                                </div>
                            </div>
                        )}
                        <div className="flex items-center px-6 py-3 border-b focus-within:bg-muted/30 transition-colors group">
                            <Label htmlFor="compose-subject" className="w-16 text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('Subject')}</Label>
                            <Input
                                id="compose-subject"
                                value={composeSubject}
                                onChange={(e) => setComposeSubject(e.target.value)}
                                placeholder={t('Enter subject here...')}
                                className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto font-semibold bg-transparent text-sm"
                            />
                        </div>
                        <div className="flex flex-col relative focus-within:bg-muted/10 transition-colors duration-300">
                            <div className="min-h-[15.625rem] cursor-text" onClick={() => composeEditor?.commands.focus()}>
                                <EditorContent editor={composeEditor} />
                            </div>
                            {/* Compose attachment previews */}
                            {composeFiles.length > 0 && (
                                <div className="flex flex-wrap gap-2 px-5 py-3 border-t bg-muted/10">
                                    {composeFiles.map((file, idx) => (
                                        <div key={idx} className="flex items-center gap-2 bg-background border rounded-lg px-3 py-2 text-xs shadow-sm">
                                            {file.type.startsWith('image/') ? (
                                                <img src={URL.createObjectURL(file)} alt={file.name} className="h-8 w-8 rounded object-cover" />
                                            ) : (
                                                <FileText className="h-4 w-4 text-primary shrink-0" />
                                            )}
                                            <span className="truncate max-w-[140px]">{file.name}</span>
                                            <button onClick={() => setComposeFiles(prev => prev.filter((_, i) => i !== idx))} className="text-muted-foreground hover:text-destructive">
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {/* Formatting Toolbar (Toggled by T button) */}
                            {showFormatting && composeEditor && (
                                <div className="flex items-center gap-0.5 px-5 py-1.5 border-t bg-muted/5 animate-in slide-in-from-bottom-1 duration-200">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={cn("h-8 w-8 p-0", composeEditor.isActive('bold') && "bg-muted text-primary")}
                                        onClick={() => composeEditor.chain().focus().toggleBold().run()}
                                    >
                                        <Bold className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={cn("h-8 w-8 p-0", composeEditor.isActive('italic') && "bg-muted text-primary")}
                                        onClick={() => composeEditor.chain().focus().toggleItalic().run()}
                                    >
                                        <Italic className="h-4 w-4" />
                                    </Button>
                                    <div className="w-px h-4 bg-border mx-1" />
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                                        onClick={() => composeEditor.chain().focus().unsetAllMarks().run()}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            )}
                            {/* Main Toolbar */}
                            <div className="flex items-center justify-between px-5 py-2 border-t bg-background relative z-10 shadow-[0_-4px_10px_rgba(0,0,0,0.02)]">
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className={cn("h-8 w-8 transition-colors rounded-full", showFormatting ? "text-primary bg-primary/10" : "text-muted-foreground hover:text-foreground hover:bg-muted/50")}
                                        onClick={() => setShowFormatting(!showFormatting)}
                                    >
                                        <Type className="h-4 w-4" />
                                    </Button>
                                    <div className="w-px h-5 bg-border mx-2" />
                                    <input type="file" multiple ref={composeFileRef} className="hidden" onChange={(e) => { if (e.target.files) setComposeFiles(prev => [...prev, ...Array.from(e.target.files!)]); e.target.value = ''; }} />
                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors" onClick={() => composeFileRef.current?.click()}><Paperclip className="h-4 w-4" /></Button>
                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors" onClick={() => composeFileRef.current?.click()}><ImageIcon className="h-4 w-4" /></Button>
                                    <EmojiPicker
                                        onSelect={(emoji) => composeEditor?.chain().focus().insertContent(emoji).run()}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <DialogFooter className="px-5 py-4 bg-muted/10 sm:justify-end items-center rounded-b-xl">
                        <div className="flex items-center gap-3">
                            <Button variant="ghost" size="sm" onClick={() => setShowCompose(false)} disabled={isComposing} className="text-xs font-semibold px-4 h-9">
                                {t('Cancel')}
                            </Button>
                            <Button size="sm" onClick={handleSendNewEmail} disabled={isComposing || !composeTo.trim() || !composeSubject.trim() || !composeBody.trim()} className="gap-2 px-6 h-9 shadow-md rounded-full font-bold tracking-wide">
                                {isComposing ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                {t('Send Message')}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Follow-up Sequence Dialog */}
            <Dialog open={showFollowUpModal} onOpenChange={setShowFollowUpModal}>
                <DialogContent className="w-[95vw] sm:max-w-[550px] p-0 overflow-hidden border-none shadow-2xl rounded-xl">
                    <DialogHeader className="px-6 py-4 bg-primary/5 border-b">
                        <DialogTitle className="text-lg font-semibold flex items-center text-primary">
                            <Clock className="w-5 h-5 mr-2" />
                            {t('Auto Follow-up Sequence')}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="bg-background max-h-[85vh] overflow-hidden">
                        <FollowUpSequenceBuilder
                            threadId={selectedThread?.id}
                            lastMessageAt={selectedThread?.last_message_at}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <CrudFormModal
                isOpen={isLeadModalOpen}
                onClose={() => setIsLeadModalOpen(false)}
                onSubmit={handleLeadFormSubmit}
                formConfig={{
                    fields: [
                        { name: 'name', label: t('Lead Name'), type: 'text', required: true },
                        { name: 'email', label: t('Email'), type: 'email' },
                        { name: 'phone', label: t('Phone'), type: 'tel' },
                        { name: 'company', label: t('Company'), type: 'text' },
                        { name: 'account_name', label: t('Account Name'), type: 'text' },
                        {
                            name: 'account_industry_id',
                            label: t('Account Industry'),
                            type: 'select',
                            options: accountIndustries.map((industry: any) => ({ value: industry.id, label: industry.name }))
                        },
                        { name: 'website', label: t('Website'), type: 'text' },
                        { name: 'position', label: t('Position'), type: 'text' },
                        { name: 'value', label: t('Lead Value'), type: 'number', step: '0.01', min: '0' },
                        {
                            name: 'lead_status_id',
                            label: t('Lead Status'),
                            type: 'select',
                            required: true,
                            options: leadStatuses.map((status: any) => ({
                                value: status.id,
                                label: status.name
                            }))
                        },
                        {
                            name: 'lead_source_id',
                            label: t('Lead Source'),
                            type: 'select',
                            required: true,
                            options: leadSources.map((source: any) => ({
                                value: source.id,
                                label: source.name
                            }))
                        },
                        { name: 'address', label: t('Address'), type: 'textarea' },
                        {
                            name: 'campaign_id',
                            label: t('Campaign'),
                            type: 'select',
                            options: campaigns.map((campaign: any) => ({ value: campaign.id, label: campaign.name }))
                        },
                        { name: 'notes', label: t('Notes'), type: 'textarea' },
                        {
                            name: 'assigned_to',
                            label: t('Assign To'),
                            type: 'select',
                            options: users.map((user: any) => ({ value: user.id, label: `${(user.display_name || user.name)} (${user.email})` })),
                            hidden: !isOwner
                        },
                        {
                            name: 'status',
                            label: t('Status'),
                            type: 'select',
                            options: [
                                { value: 'active', label: t('Active') },
                                { value: 'inactive', label: t('Inactive') }
                            ],
                            defaultValue: 'active'
                        }
                    ],
                    modalSize: 'xl'
                }}
                initialData={leadInitialData}
                title={t('Add New Lead')}
                mode="create"
            />

            </div>

        </PageTemplate>
    );
}
