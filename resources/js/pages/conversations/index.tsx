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
    Link,
    Type,
    Trash2,
    Calendar,
    History as HistoryIcon,
    ChevronRight,
    Clock,
    UserCheck,
    CheckCircle,
} from 'lucide-react';
import { ActivityStream } from '@/components/ActivityStream/ActivityStream';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import { CrudFormModal } from '@/components/CrudFormModal';
import { hasPermission } from '@/utils/authorization';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
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
import { format } from 'date-fns';

/* ── helpers ───────────────────────────────────────────────── */
const parseUTC = (dateStr: string) => {
    if (!dateStr) return new Date();
    return dateStr.endsWith('Z') ? new Date(dateStr) : new Date(dateStr + 'Z');
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
const FolderTabs = ({ selectedFolder, onSelect, unreadCount, t, onCompose }: any) => {
    const folders = [
        { key: 'inbox',      icon: Inbox,           label: t('Inbox'),      count: unreadCount },
        { key: 'my_assignments', icon: UserCheck,   label: t('My Assignments'), count: 0 },
        { key: 'unassigned', icon: Archive,         label: t('Unassigned'), count: 0 },
        { key: 'sent',       icon: Send,            label: t('Sent'),       count: 0 },
        { key: 'closed',     icon: CheckCircle,     label: t('Closed'),     count: 0 },
        { key: 'history',    icon: HistoryIcon,     label: t('History'),    count: 0 },
    ];
    return (
        <div className="flex gap-1 p-2 overflow-x-auto items-center">
            <Button size="sm" onClick={onCompose} className="h-7 px-3 text-xs gap-1.5 shrink-0 mr-1.5 text-primary-foreground">
                <PenBox className="h-3.5 w-3.5" />
                {t('Compose')}
            </Button>
            {folders.map(f => (
                <button
                    key={f.key}
                    onClick={() => onSelect(f.key)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md whitespace-nowrap transition-colors ${
                        selectedFolder === f.key
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-muted'
                    }`}
                >
                    <f.icon className="h-3.5 w-3.5" />
                    {f.label}
                    {f.count > 0 && (
                        <span className={`text-[10px] px-1 py-0 rounded-full ${
                            selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20'
                        }`}>{f.count}</span>
                    )}
                </button>
            ))}
        </div>
    );
};

/* ── Full sidebar for xl+ screens ──────────────────────────── */
const FolderSidebar = ({ selectedFolder, onSelect, unreadCount, t, isSyncing, onSync, onCompose }: any) => (
    <div className="hidden xl:flex w-[180px] border-r flex-col bg-muted/30 shrink-0">
        <div className="p-3 flex-1">
            <div className="flex items-center justify-between mb-3 px-1">
                <span className="text-sm font-semibold">{t('Conversations')}</span>
                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={onSync} disabled={isSyncing}>
                    <RefreshCw className={`h-3.5 w-3.5 ${isSyncing ? 'animate-spin text-primary' : ''}`} />
                </Button>
            </div>

            <Button className="w-full mb-4 h-8 text-xs font-semibold gap-1.5 shadow-sm" onClick={onCompose}>
                <PenBox className="h-3.5 w-3.5" />
                {t('Compose')}
            </Button>

            <div className="space-y-0.5">
                {[
                    { key: 'inbox',      icon: Inbox,           label: t('Inbox'),      count: unreadCount },
                    { key: 'my_assignments', icon: UserCheck,   label: t('My Assignments'), count: 0 },
                    { key: 'unassigned', icon: Archive,         label: t('Unassigned'), count: 0 },
                    { key: 'unassigned_staff', icon: UserPlus,  label: t('Unassigned Staff'), count: 0 },
                    { key: 'sent',       icon: Send,            label: t('Sent'),       count: 0 },
                    { key: 'closed',     icon: CheckCircle,     label: t('Closed'),     count: 0 },
                    { key: 'history',    icon: HistoryIcon,     label: t('History'),    count: 0 },
                ].map(f => (
                    <button
                        key={f.key}
                        onClick={() => onSelect(f.key)}
                        className={`w-full flex items-center justify-between px-2.5 py-1.5 text-xs rounded-md transition-colors ${
                            selectedFolder === f.key ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-muted text-muted-foreground'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <f.icon className="h-3.5 w-3.5" />
                            {f.label}
                        </span>
                        {f.count > 0 && (
                            <span className={`text-[10px] px-1.5 py-0 rounded-full ${
                                selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20 text-muted-foreground'
                            }`}>{f.count}</span>
                        )}
                    </button>
                ))}
            </div>
        </div>
    </div>
);

/* ── Main component ────────────────────────────────────────── */
export default function ConversationsIndex({ gmailAccount, companyId, isOwner, unreadCount: initialUnreadCount }: { gmailAccount: any, companyId: number, isOwner: boolean, unreadCount?: number }) {
    const { t } = useTranslation();
    const { auth, leadStatuses = [], leadSources = [], accountIndustries = [], campaigns = [], users = [] } = usePage<any>().props;
    const permissions = auth?.permissions || [];
    const [selectedFolder, setSelectedFolder] = useState('inbox');
    const [threads, setThreads] = useState<any[]>([]);
    const [selectedThread, setSelectedThread] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [showContactSidebar, setShowContactSidebar] = useState(false);
    const [replyBody, setReplyBody] = useState('');
    const [submittingReply, setSubmittingReply] = useState(false);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount || 0);
    const [searchQuery, setSearchQuery] = useState('');
    const [historyActivities, setHistoryActivities] = useState<any[]>([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [loadingParticipants, setLoadingParticipants] = useState(false);
    const [historyParticipants, setHistoryParticipants] = useState<any[]>([]);

    // Pagination states
    const [threadPage, setThreadPage] = useState(1);
    const [hasMoreThreads, setHasMoreThreads] = useState(false);
    const [historyPage, setHistoryPage] = useState(1);
    const [hasMoreHistory, setHasMoreHistory] = useState(false);
    const [participantsPage, setParticipantsPage] = useState(1);
    const [hasMoreParticipants, setHasMoreParticipants] = useState(false);
    const [isSyncingHistory, setIsSyncingHistory] = useState(false);
    const [gmailPageToken, setGmailPageToken] = useState<string | null>(undefined); // undefined = haven't checked Gmail yet
    const [selectedParticipant, setSelectedParticipant] = useState<any>(null);
    const [searchParticipants, setSearchParticipants] = useState('');
    
    // Feature states
    const [companyUsers, setCompanyUsers] = useState<any[]>([]);
    const [updatingMetadata, setUpdatingMetadata] = useState(false);
    
    // Internal thread message pagination
    const [messagesPage, setMessagesPage] = useState(1);
    const [hasMoreMessages, setHasMoreMessages] = useState(false);
    const [loadingMoreMessages, setLoadingMoreMessages] = useState(false);

    const [showCompose, setShowCompose] = useState(false);
    const [composeTo, setComposeTo] = useState('');
    const [composeSubject, setComposeSubject] = useState('');
    const [composeBody, setComposeBody] = useState('');
    const [isComposing, setIsComposing] = useState(false);
    const [composeFiles, setComposeFiles] = useState<File[]>([]);
    const [replyFiles, setReplyFiles] = useState<File[]>([]);
    const composeFileRef = React.useRef<HTMLInputElement>(null);
    const replyFileRef = React.useRef<HTMLInputElement>(null);
    const threadObserverTarget = React.useRef<HTMLDivElement>(null);
    const participantObserverTarget = React.useRef<HTMLDivElement>(null);
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
    useEffect(() => { selectedThreadIdRef.current = selectedThread?.id || null; }, [selectedThread?.id]);

    useEffect(() => {
        if (selectedFolder === 'history') {
            if (selectedParticipant) {
                setHistoryPage(1);
                setGmailPageToken(undefined); // Reset token for new participant
                fetchParticipantActivities(selectedParticipant.email, false);
            } else {
                setParticipantsPage(1);
                fetchHistoryParticipants(false);
            }
        } else {
            setThreadPage(1);
            fetchThreads(false);
        }
        
        if (!companyId) return;

        const channel = getEcho().private(`company.${companyId}`)
            .listen('.gmail.sync.completed', (data: any) => {
                if (gmailAccount && data.gmailAccountId == gmailAccount.id) {
                if (selectedFolder === 'history') {
                    if (selectedParticipant) {
                        fetchParticipantActivities(selectedParticipant.email, false, true);
                    } else {
                        fetchHistoryParticipants(false, true);
                    }
                } else {
                    fetchThreads(false, true);
                }
                    if (selectedThreadIdRef.current) {
                        axios.get(route('api.conversations.show', selectedThreadIdRef.current))
                            .then(r => setSelectedThread(r.data))
                            .catch(err => console.error('Silent refresh failed:', err));
                    }
                }
            });
        return () => { channel.stopListening('.gmail.sync.completed'); };
    }, [selectedFolder, gmailAccount?.id, companyId, selectedParticipant?.email, searchQuery, searchParticipants]);

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

    // Participant Infinite Scroll Observer
    useEffect(() => {
        const observer = new IntersectionObserver(
            entries => {
                if (entries[0].isIntersecting && hasMoreParticipants && !loadingParticipants) {
                    fetchHistoryParticipants(true);
                }
            },
            { threshold: 0.1 }
        );
        if (participantObserverTarget.current) observer.observe(participantObserverTarget.current);
        return () => observer.disconnect();
    }, [hasMoreParticipants, loadingParticipants]);

    const fetchHistoryParticipants = async (append = false, silent = false) => {
        if (!silent) {
            setLoadingParticipants(true);
            if (!append) setHistoryParticipants([]); // Clear previous on fresh load
        }
        try {
            const page = append ? participantsPage + 1 : 1;
            const params: any = { page };
            if (searchParticipants.trim()) params.search = searchParticipants.trim();
            
            const response = await axios.get(route('api.conversations.history.participants', params));
            const { data, current_page, last_page } = response.data;
            
            setHistoryParticipants(prev => {
                const combined = append ? [...prev, ...data] : data;
                // Unique by email
                const unique = Array.from(new Map<string, any>(combined.map((item: any) => [item.email, item])).values());
                return unique;
            });
            setParticipantsPage(current_page);
            setHasMoreParticipants(current_page < last_page);
        } catch (error) {
            console.error('Failed to fetch history participants:', error);
            if (!silent) toast.error(t('Failed to load history list'));
        } finally {
            if (!silent) setLoadingParticipants(false);
        }
    };

    const fetchParticipantActivities = async (email: string, append = false, silent = false) => {
        if (!silent) {
            setLoadingHistory(true);
            if (!append) setHistoryActivities([]); // Clear previous on fresh load
        }
        try {
            const page = append ? historyPage + 1 : 1;
            const response = await axios.get(route('api.conversations.activities', { email, page }));
            const { data, current_page, last_page } = response.data;

            setHistoryActivities(prev => {
                const combined = append ? [...prev, ...data] : data;
                // Unique by ID
                const unique = Array.from(new Map<number, any>(combined.map((item: any) => [item.id, item])).values());
                return unique;
            });
            setHistoryPage(current_page);
            setHasMoreHistory(current_page < last_page);
        } catch (error) {
            console.error('Failed to fetch participant history:', error);
            if (!silent) toast.error(t('Failed to load activity timeline'));
        } finally {
            if (!silent) setLoadingHistory(false);
        }
    };

    const fetchThreads = async (append = false, silent = false) => {
        if (!silent) setLoading(true);
        try {
            const page = append ? threadPage + 1 : 1;
            const params: any = { folder: selectedFolder, page };
            if (searchQuery.trim()) params.search = searchQuery.trim();
            
            const response = await axios.get(route('api.conversations.threads', params));
            const { data, current_page, last_page } = response.data;

            if (append) {
                setThreads(prev => {
                    const combined = [...prev, ...data];
                    // Unique by ID
                    const unique = Array.from(new Map<number, any>(combined.map((item: any) => [item.id, item])).values());
                    return unique;
                });
            } else {
                setThreads(data);
                setUnreadCount(data.filter((t: any) => !t.is_read).length);
            }
            
            setThreadPage(current_page);
            setHasMoreThreads(current_page < last_page);

            // If we reached the end of local threads in inbox/sent, try syncing more from Gmail
            if (append && current_page >= last_page && (selectedFolder === 'inbox' || selectedFolder === 'sent')) {
                handleSeamlessInboxSync();
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

    const handleSeamlessInboxSync = async () => {
        if (loading || isSyncing) return;
        
        setLoading(true);
        try {
            const response = await axios.post(route('api.conversations.sync_inbox_more'));
            if (response.data.success && response.data.stats.synced > 0) {
                // We fetched new threads! Now pull them from the database
                await fetchThreads(true, true);
            } else {
                // No more threads in Gmail either
                setHasMoreThreads(false);
            }
        } catch (error) {
            console.error('Failed to sync more inbox threads:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSeamlessSync = async () => {
        if (!selectedParticipant?.email || isSyncingHistory) return;
        
        setIsSyncingHistory(true);
        try {
            const response = await axios.post(route('api.conversations.history.sync'), {
                email: selectedParticipant.email,
                pageToken: gmailPageToken === undefined ? null : gmailPageToken
            });
            
            setGmailPageToken(response.data.nextPageToken || null);
            
            // Re-fetch local activities to show the newly synced ones
            // We append to the current view
            fetchParticipantActivities(selectedParticipant.email, true, true);
        } catch (error: any) {
            console.error('Seamless sync failed:', error);
            // Silent failure, but stop trying if 401 persists
            setGmailPageToken(null); 
        } finally {
            setIsSyncingHistory(false);
        }
    };

    const handleSelectThread = async (thread: any, page = 1) => {
        if (page === 1) setLoading(true);
        else setLoadingMoreMessages(true);

        try {
            const response = await axios.get(route('api.conversations.show', thread.id), {
                params: { page }
            });
            
            const newThread = response.data.thread;
            const pagination = response.data.messages_pagination;

            if (page === 1) {
                setSelectedThread(newThread);
                setMessagesPage(1);
                setHasMoreMessages(pagination.has_more);
                // Initial load: scroll to bottom
                setTimeout(scrollToBottom, 100);
            } else {
                // Infinite scroll up: prepend messages
                setSelectedThread((prev: any) => {
                    if (!prev || prev.id !== newThread.id) return prev;
                    return {
                        ...prev,
                        messages: [...newThread.messages, ...prev.messages]
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

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const fetchEarlierMessages = () => {
        if (!selectedThread || !hasMoreMessages || loadingMoreMessages) return;
        handleSelectThread(selectedThread, messagesPage + 1);
    };

    const handleSendReply = async () => {
        if (!replyBody.trim()) return;
        setSubmittingReply(true);
        try {
            const formData = new FormData();
            formData.append('body', replyBody);
            replyFiles.forEach((file) => formData.append('attachments[]', file));
            await axios.post(route('api.conversations.reply', selectedThread.id), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toast.success(t('Reply sent successfully'));
            setReplyBody('');
            setReplyFiles([]);
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
            composeFiles.forEach((file) => formData.append('attachments[]', file));
            await axios.post(route('api.conversations.compose'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            toast.success(t('Email sent successfully'));
            setShowCompose(false);
            setComposeTo('');
            setComposeSubject('');
            setComposeBody('');
            setComposeFiles([]);
        } catch (error: any) {
            toast.error(error.response?.data?.error || t('Failed to send email'));
        } finally {
            setIsComposing(false);
        }
    };

    const handleBack = () => {
        setSelectedThread(null);
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
        >
            <Head title={t('Conversations Hub')} />

            {/* 
                Main container: fills available height.
                PageTemplate with noPadding gives us the full content area.
                The outer layout (AppLayout) has a top nav bar (~56px) + breadcrumb (~40px) + 
                the PageTemplate header (~48px) + padding (p-4 = 16px top/bottom + gap-4 = 16px).
                Total overhead ≈ 56 + 40 + 48 + 32 + 16 = ~192px. Use 200px for safety.
            */}
            <div className="flex flex-col h-[calc(100vh-200px)] min-h-[400px] border rounded-xl bg-background shadow-sm overflow-hidden relative">
                
                {/* Mobile folder tabs: visible below xl where the sidebar is hidden */}
                <div className="xl:hidden border-b shrink-0">
                    <FolderTabs 
                        selectedFolder={selectedFolder} 
                        onSelect={setSelectedFolder} 
                        unreadCount={unreadCount} 
                        t={t} 
                        onCompose={() => setShowCompose(true)}
                    />
                </div>

                {/* Main flex row: sidebar + list + detail */}
                <div className="flex flex-1 min-h-0 overflow-hidden">
                    
                    {/* Pane 1: Folder sidebar (xl+ only) */}
                    <FolderSidebar
                        selectedFolder={selectedFolder}
                        onSelect={setSelectedFolder}
                        unreadCount={unreadCount}
                        t={t}
                        isSyncing={isSyncing}
                        onSync={handleSync}
                        onCompose={() => setShowCompose(true)}
                    />

                    {selectedFolder === 'history' ? (
                        <div className="flex-1 min-h-0 overflow-hidden bg-muted/5 p-4 lg:p-6 flex flex-col">
                            {selectedParticipant ? (
                                <div className="flex flex-col h-full">
                                    <div className="flex items-center justify-between mb-4 bg-background p-3 rounded-lg border shadow-sm">
                                        <div className="flex items-center gap-3 shadow-sm p-1 rounded-md">
                                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setSelectedParticipant(null)}>
                                                <ArrowLeft className="h-4 w-4" />
                                            </Button>
                                            <div className="flex items-center gap-3">
                                                <Avatar className="h-10 w-10 border shadow-sm">
                                                    <AvatarFallback className="bg-primary/10 text-primary font-bold">
                                                        {selectedParticipant.name?.charAt(0).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <h3 className="text-sm font-bold truncate max-w-[120px] lg:max-w-none">{selectedParticipant.name}</h3>
                                                    <p className="text-[10px] text-muted-foreground truncate max-w-[120px] lg:max-w-none">{selectedParticipant.email}</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center gap-2">
                                            <Button variant="outline" size="sm" className="h-8 text-xs font-semibold" onClick={() => {
                                                setComposeTo(selectedParticipant.email);
                                                setShowCompose(true);
                                            }}>
                                                <Mail className="h-3.5 w-3.5 mr-1.5" />
                                                {t('Email')}
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="flex-1 min-h-0 overflow-y-auto">
                                        {loadingHistory && historyActivities.length === 0 ? (
                                            <div className="flex flex-col items-center justify-center h-64 space-y-4">
                                                <div className="relative">
                                                    <div className="h-12 w-12 rounded-full border-t-2 border-b-2 border-primary animate-spin"></div>
                                                    <HistoryIcon className="h-4 w-4 text-primary absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                                                </div>
                                                <p className="text-sm font-medium text-muted-foreground animate-pulse">{t('Loading activity timeline...')}</p>
                                            </div>
                                        ) : (
                                            <ActivityStream
                                                title={t('Activity Timeline')}
                                                emptyMessage={t('No activities found for this contact.')}
                                                activities={historyActivities}
                                                isCompany={isOwner}
                                                auth={{ user: auth?.user }}
                                                maxHeight="max-h-full"
                                                hasMore={hasMoreHistory || (gmailPageToken !== null)}
                                                onLoadMore={() => {
                                                    if (hasMoreHistory) {
                                                        fetchParticipantActivities(selectedParticipant.email, true);
                                                    } else if (gmailPageToken !== null) {
                                                        handleSeamlessSync();
                                                    }
                                                }}
                                                isLoadingMore={loadingHistory || isSyncingHistory}
                                            />
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col h-full">
                                    <div className="flex items-center justify-between mb-5">
                                        <div>
                                            <h2 className="text-lg font-bold tracking-tight">{t('Communication History')}</h2>
                                            <p className="text-xs text-muted-foreground">{t('Browse activities by contact')}</p>
                                        </div>
                                        <div className="relative w-64">
                                            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                                            <Input
                                                placeholder={t('Search contacts...')}
                                                className="pl-8 h-8 text-xs bg-background"
                                                value={searchParticipants}
                                                onChange={(e) => setSearchParticipants(e.target.value)}
                                                onKeyDown={(e) => { if (e.key === 'Enter') fetchHistoryParticipants(); }}
                                            />
                                        </div>
                                    </div>

                                    {loadingParticipants && !historyParticipants.length ? (
                                            <div className="flex flex-col items-center justify-center py-20 animate-pulse">
                                                <RefreshCw className="h-8 w-8 text-primary/20 animate-spin mb-3" />
                                                <p className="text-sm text-muted-foreground">{t('Loading contacts...')}</p>
                                            </div>
                                        ) : historyParticipants.length > 0 ? (
                                        <ScrollArea className="flex-1 min-h-0">
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 pr-2">
                                                {historyParticipants.map((p: any) => (
                                                    <button
                                                        key={p.email}
                                                        onClick={() => setSelectedParticipant(p)}
                                                        className="flex items-start gap-4 p-4 rounded-xl border bg-background hover:border-primary/50 hover:shadow-md transition-all text-left group overflow-hidden"
                                                    >
                                                        <Avatar className="h-10 w-10 border group-hover:scale-105 transition-transform">
                                                            <AvatarFallback className="bg-primary/5 text-primary text-sm font-bold">
                                                                {p.name?.charAt(0).toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex-1 min-w-0">
                                                            <h4 className="font-bold text-sm truncate mb-0.5 group-hover:text-primary transition-colors">
                                                                {p.name}
                                                            </h4>
                                                            <p className="text-[11px] text-muted-foreground truncate mb-2">
                                                                {p.email}
                                                            </p>
                                                            <div className="flex items-center justify-between mt-auto pt-2 border-t">
                                                                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                                                    <Clock className="h-3 w-3" />
                                                                    {timeAgoShort(p.last_activity_at)}
                                                                </div>
                                                                <ChevronRight className="h-3.5 w-3.5 text-muted-foreground group-hover:translate-x-0.5 transition-transform" />
                                                            </div>
                                                        </div>
                                                    </button>
                                                ))}
                                                {/* Sentinel for IntersectionObserver */}
                                                <div ref={participantObserverTarget} className="h-4 w-full" />

                                                {loadingParticipants && (
                                                    <div className="col-span-full p-4 flex justify-center items-center gap-2 text-primary/60 animate-pulse">
                                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                                        <span className="text-[10px] font-medium">{t('Loading more contacts...')}</span>
                                                    </div>
                                                )}

                                                {!hasMoreParticipants && !loadingParticipants && historyParticipants.length > 10 && (
                                                    <div className="col-span-full p-8 text-center">
                                                        <div className="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent mb-4" />
                                                        <p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('End of contacts')}</p>
                                                    </div>
                                                )}
                                            </div>
                                        </ScrollArea>
                                    ) : (
                                        <div className="flex-1 flex flex-col items-center justify-center text-center p-8 bg-background border rounded-2xl border-dashed">
                                            <div className="h-16 w-16 bg-muted/50 rounded-full flex items-center justify-center mb-4">
                                                <HistoryIcon className="h-8 w-8 text-muted-foreground/30" />
                                            </div>
                                            <h3 className="text-base font-bold mb-1">{t('No contacts with history')}</h3>
                                            <p className="text-xs text-muted-foreground max-w-xs mb-6">
                                                {t('Your communication history will appear here once you start receiving or sending emails.')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ) : (
                        <>
                            {/* Pane 2: Thread list */}
                    <div className={`
                        border-r flex flex-col bg-background overflow-hidden min-w-0
                        w-full
                        lg:w-[280px] lg:max-w-[280px] xl:w-[280px] xl:max-w-[280px] 2xl:w-[320px] 2xl:max-w-[320px]
                        lg:shrink-0
                        ${selectedThread ? 'hidden lg:flex' : 'flex'}
                    `}>
                        {/* Search bar + sync (mobile sync is here since sidebar is hidden) */}
                        <div className="p-3 border-b shrink-0">
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                                    <Input 
                                        placeholder={t('Search threads...')} 
                                        className="pl-8 bg-muted/50 border-none h-8 text-xs focus-visible:ring-1"
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
                        <ScrollArea className="flex-1 min-h-0 [&_[data-radix-scroll-area-viewport]>div]:!block">
                            {!gmailAccount ? (
                                /* No Gmail account */
                                <div className="flex flex-col items-center justify-center text-center px-4 py-10">
                                    <div className="h-12 w-12 bg-primary/10 rounded-full flex items-center justify-center mb-3">
                                        <AlertCircle className="h-6 w-6 text-primary" />
                                    </div>
                                    <h3 className="text-sm font-semibold mb-1">{t('Email Not Connected')}</h3>
                                    <p className="text-xs text-muted-foreground mb-4 max-w-[200px]">
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
                                    <p className="text-xs text-muted-foreground mb-4 max-w-[200px]">
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
                                            className={`w-full text-left py-3 pl-3 pr-5 lg:pr-4 hover:bg-muted/50 transition-colors flex items-start gap-2.5 overflow-hidden min-w-0 ${
                                                selectedThread?.id === thread.id ? 'bg-primary/5 border-l-2 border-primary' : ''
                                            }`}
                                        >
                                            <Avatar className="h-8 w-8 shrink-0 border border-primary/10">
                                                <AvatarFallback className="bg-primary/5 text-primary text-[10px] font-bold">
                                                    {(thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0])?.charAt(0).toUpperCase() || 'U'}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex justify-between items-center gap-2 mb-0.5 overflow-hidden">
                                                    <div className="flex flex-col min-w-0 flex-1">
                                                        <span className={`text-xs truncate ${
                                                            !thread.is_read ? 'font-extrabold' : 'font-semibold'
                                                        } ${selectedThread?.id === thread.id ? 'text-primary' : 'text-foreground'}`}>
                                                            {thread.leads?.[0]?.name || thread.contacts?.[0]?.name || thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0] || 'Unknown'}
                                                        </span>
                                                        {(thread.leads?.[0] || thread.contacts?.[0]) && (
                                                            <span className="text-[9px] text-muted-foreground truncate opacity-70">
                                                                {thread.leads?.[0]?.email || thread.contacts?.[0]?.email}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <span className="text-[10px] text-muted-foreground/80 truncate shrink-0 max-w-[80px] text-right">
                                                        {timeAgoShort(thread.last_message_at)}
                                                    </span>
                                                </div>
                                                <div className={`text-xs truncate mb-0.5 ${!thread.is_read ? 'font-bold text-foreground' : 'text-foreground/80'}`}>
                                                    {thread.subject || t('(No Subject)')}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground/70 truncate">
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
                                                        <Badge variant="outline" className={`text-[9px] font-bold px-1 py-0 ${
                                                            thread.priority === 'High' ? 'bg-destructive/10 text-destructive border-destructive/20' :
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
                                            <span className="text-[10px] font-medium">{t('Loading more...')}</span>
                                        </div>
                                    )}

                                    {!hasMoreThreads && !loading && threads.length > 10 && (
                                        <div className="p-6 text-center bg-muted/5">
                                            <p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('All threads loaded')}</p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                /* Empty state */
                                <div className="flex flex-col items-center justify-center text-center px-4 py-10">
                                    <div className="h-12 w-12 bg-muted/50 rounded-full flex items-center justify-center mb-3">
                                        <Inbox className="h-6 w-6 text-muted-foreground/30" />
                                    </div>
                                    <h3 className="text-sm font-semibold mb-1">{t('No conversations found')}</h3>
                                    <p className="text-xs text-muted-foreground mb-4 max-w-[180px]">
                                        {gmailAccount?.sync_status === 'syncing' 
                                            ? t('We are currently syncing your inbox...')
                                            : t('Try clicking the sync button to fetch your latest emails.')}
                                    </p>
                                    {gmailAccount?.sync_error && (
                                        <div className="p-2 bg-destructive/5 text-destructive border border-destructive/10 rounded-lg text-[10px] mb-3 max-w-[200px]">
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
                    </div>

                    {/* Pane 3: Thread detail + reply */}
                    <div className={`
                        flex-1 flex flex-col min-w-0 overflow-hidden bg-muted/5
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
                                            <h2 className="text-sm font-semibold truncate">
                                                {selectedThread.subject || t('(No Subject)')}
                                            </h2>
                                            <div className="text-[11px] text-muted-foreground flex items-center gap-1.5">
                                                <span className="truncate max-w-[200px]">{selectedThread.participants?.join(', ')}</span>
                                                <span>·</span>
                                                <span className="shrink-0">{selectedThread.message_count} {t('messages')}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 ml-2 shrink-0">
                                        {/* Status Picker */}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="outline" size="sm" className="h-8 text-[11px] font-bold gap-1.5 px-2.5">
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
                                            </DropdownMenuContent>
                                        </DropdownMenu>

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
                                                <DropdownMenuLabel className="text-[10px] uppercase text-muted-foreground">{t('Thread Priority')}</DropdownMenuLabel>
                                                {['Low', 'Medium', 'High'].map(p => (
                                                    <DropdownMenuItem key={p} onClick={() => handleUpdateMetadata({ priority: p })}>
                                                        <div className={`w-2.5 h-2.5 rounded-full mr-2 ${
                                                            p === 'High' ? 'bg-destructive' : p === 'Medium' ? 'bg-amber-500' : 'bg-blue-500'
                                                        }`} />
                                                        {t(p)}
                                                        {selectedThread.priority === p && <CheckCircle className="ml-auto h-3 w-3 text-primary" />}
                                                    </DropdownMenuItem>
                                                ))}
                                                <DropdownMenuSeparator />
                                                <DropdownMenuLabel className="text-[10px] uppercase text-muted-foreground">{t('Follow-up Date')}</DropdownMenuLabel>
                                                <div className="px-2 py-1.5">
                                                    <DatePicker
                                                        selected={selectedThread.follow_up_at ? new Date(selectedThread.follow_up_at) : undefined}
                                                        onChange={(date: Date | undefined) => handleUpdateMetadata({ follow_up_at: date ? date.toISOString() : null })}
                                                    />
                                                </div>
                                            </DropdownMenuContent>
                                        </DropdownMenu>

                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setShowContactSidebar(!showContactSidebar)}>
                                            <User className={`h-4 w-4 ${showContactSidebar ? 'text-primary' : ''}`} />
                                        </Button>
                                    </div>
                                </div>

                                {/* Messages */}
                                <ScrollArea className="flex-1 min-h-0">
                                    <div className="p-3 lg:p-4 lg:p-6 space-y-4 lg:space-y-6 max-w-4xl mx-auto">
                                        {/* Observer target for loading older history at the top */}
                                        <div ref={messagesTopObserverTarget} className="h-1 w-full" />
                                        
                                        {loadingMoreMessages && (
                                            <div className="flex justify-center items-center py-2 text-muted-foreground text-[10px] italic">
                                                <RefreshCw className="h-3 w-3 animate-spin mr-2" />
                                                {t('Loading earlier messages...')}
                                            </div>
                                        )}

                                        {selectedThread.messages?.map((msg: any) => (
                                            <div key={msg.id} className="flex gap-2 lg:gap-3">
                                                <Avatar className="h-7 w-7 lg:h-8 lg:w-8 shrink-0 border">
                                                    {msg.sender?.avatar ? (
                                                        <AvatarImage src={msg.sender.avatar} />
                                                    ) : null}
                                                    <AvatarFallback className="bg-muted text-[10px]">
                                                        {(msg.sender?.name || msg.from_email)?.charAt(0).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0 space-y-1.5">
                                                    <div className="flex items-start justify-between gap-2 overflow-hidden min-w-0">
                                                        <span className="text-xs font-semibold truncate flex-1 min-w-0">
                                                            {msg.from_name || msg.from_email}
                                                            {msg.sender && (
                                                                <span className="ml-1 text-[10px] font-normal text-muted-foreground italic">
                                                                    via {msg.sender.name}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground truncate shrink-0 max-w-[90px] text-right">
                                                            {timeAgo(msg.sent_at)}
                                                        </span>
                                                    </div>
                                                    <div className="bg-background border rounded-lg p-3 shadow-sm text-xs lg:text-sm leading-relaxed overflow-hidden break-words [overflow-wrap:anywhere]">
                                                        {msg.body_html ? (
                                                            <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(msg.body_html) }} />
                                                        ) : (
                                                            <span className="whitespace-pre-wrap">{msg.body_preview}</span>
                                                        )}
                                                    </div>
                                                    {/* Attachments */}
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
                                                                            className="max-w-[180px] max-h-[140px] rounded-md border object-cover hover:opacity-80 transition-opacity"
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
                                                </div>
                                            </div>
                                        ))}
                                        {/* Scroll to bottom target */}
                                        <div ref={messagesEndRef} className="h-px" />
                                    </div>
                                </ScrollArea>

                                {/* Reply box */}
                                <div className="border-t bg-background shrink-0 p-2 lg:p-3">
                                    <div className="max-w-4xl mx-auto border rounded-lg shadow-sm focus-within:ring-1 focus-within:ring-primary/30 overflow-hidden">
                                        <textarea 
                                            className="w-full min-h-[60px] lg:min-h-[80px] p-2.5 text-xs lg:text-sm bg-transparent border-none focus:ring-0 resize-none outline-none"
                                            placeholder={t('Write your reply here...')}
                                            value={replyBody}
                                            onChange={(e) => setReplyBody(e.target.value)}
                                            disabled={submittingReply}
                                        />
                                        {/* Reply attachment previews */}
                                        {replyFiles.length > 0 && (
                                            <div className="flex flex-wrap gap-2 px-2.5 py-2 border-t bg-muted/10">
                                                {replyFiles.map((file, idx) => (
                                                    <div key={idx} className="flex items-center gap-1.5 bg-background border rounded-md px-2 py-1 text-xs">
                                                        <Paperclip className="h-3 w-3 text-muted-foreground" />
                                                        <span className="truncate max-w-[120px]">{file.name}</span>
                                                        <button onClick={() => setReplyFiles(prev => prev.filter((_, i) => i !== idx))} className="text-muted-foreground hover:text-destructive">
                                                            <X className="h-3 w-3" />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between px-2.5 py-1.5 bg-muted/20 border-t">
                                            <div className="flex items-center gap-1">
                                                <input type="file" multiple ref={replyFileRef} className="hidden" onChange={(e) => { if (e.target.files) setReplyFiles(prev => [...prev, ...Array.from(e.target.files!)]); e.target.value = ''; }} />
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => replyFileRef.current?.click()} disabled={submittingReply}>
                                                    <Paperclip className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                            <Button 
                                                size="sm" 
                                                className="gap-1.5 px-4 h-7 text-xs" 
                                                onClick={handleSendReply} 
                                                disabled={submittingReply || !replyBody.trim()}
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
                    )}
                </div>

                {/* Pane 4: Contact details — ALWAYS an absolute overlay */}
                {selectedThread && showContactSidebar && (
                    <>
                        {/* Backdrop for mobile */}
                        <div 
                            className="absolute inset-0 z-20 bg-black/20 lg:bg-transparent lg:pointer-events-none" 
                            onClick={() => setShowContactSidebar(false)} 
                        />
                        <div className="absolute right-0 top-0 bottom-0 z-30 w-[280px] max-w-[85vw] border-l flex flex-col bg-background shadow-2xl overflow-y-auto">
                            <ScrollArea className="flex-1 min-h-0">
                                <div className="p-4 md:p-5">
                                    <div className="flex items-center justify-between mb-5">
                                        <h3 className="text-sm font-semibold">{t('Contact Details')}</h3>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setShowContactSidebar(false)}>
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    {/* Profile */}
                                    <div className="flex flex-col items-center text-center mb-6 w-full px-2 overflow-hidden">
                                        {(() => {
                                            const externalParticipant = selectedThread.participants?.find((p: string) => p !== gmailAccount?.email) || selectedThread.participants?.[0];
                                            const contactName = selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || externalParticipant;
                                            
                                            return (
                                                <>
                                                    <Avatar className="h-16 w-16 mb-3 border-2 border-primary/10 shrink-0">
                                                        <AvatarFallback className="text-lg font-bold bg-primary/5 text-primary">
                                                            {contactName?.charAt(0).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <h4 className="font-bold text-sm truncate w-full">
                                                        {contactName}
                                                    </h4>
                                                    <p className="text-[11px] text-muted-foreground truncate w-full">
                                                        {externalParticipant}
                                                    </p>
                                                </>
                                            );
                                        })()}
                                    </div>

                                    {/* CRM context */}
                                    <div className="space-y-4">
                                        {selectedThread.leads?.length > 0 ? (
                                            <div className="p-3 bg-blue-50/50 border border-blue-100 rounded-lg">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <Badge className="bg-blue-600 font-bold text-[10px] px-1.5 py-0">{t('LEAD')}</Badge>
                                                    <span className="text-[10px] text-blue-600 uppercase font-bold">
                                                        {selectedThread.leads[0].lead_status?.name || selectedThread.leads[0].status || t('Active')}
                                                    </span>
                                                </div>
                                                <div className="space-y-1.5 text-[11px]">
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">{t('Value')}:</span>
                                                        <span className="font-semibold">{selectedThread.leads[0].value ? `$${selectedThread.leads[0].value}` : t('N/A')}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">{t('Status')}:</span>
                                                        <Badge variant="outline" className="h-4 text-[9px]">
                                                            {selectedThread.leads[0].lead_status?.name || selectedThread.leads[0].status || t('New')}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <a href={route('leads.show', selectedThread.leads[0].id)}>
                                                    <Button variant="link" size="sm" className="p-0 h-auto mt-3 text-[11px] font-semibold">
                                                        {t('View Full Lead Record')}
                                                    </Button>
                                                </a>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center p-4 border border-dashed rounded-lg bg-muted/5">
                                                <UserPlus className="h-5 w-5 text-muted-foreground/30 mb-1.5" />
                                                
                                                {selectedThread.suggested_leads?.length > 0 ? (
                                                    <div className="w-full space-y-3">
                                                        <div className="bg-amber-50 border border-amber-100 p-2.5 rounded-md">
                                                            <p className="text-[10px] text-amber-700 font-bold uppercase mb-1 flex items-center gap-1">
                                                                <AlertCircle className="h-3 w-3" />
                                                                {t('Existing Lead Found')}
                                                            </p>
                                                            <p className="text-[11px] text-amber-900 font-medium">
                                                                {selectedThread.suggested_leads[0].name}
                                                            </p>
                                                            <p className="text-[10px] text-amber-600 truncate">
                                                                {selectedThread.suggested_leads[0].email}
                                                            </p>
                                                        </div>
                                                        <Button 
                                                            size="sm" 
                                                            className="w-full text-xs h-8 shadow-sm font-bold"
                                                            onClick={() => handleLinkToLead(selectedThread.suggested_leads[0].id)}
                                                        >
                                                            {t('Link to Existing Lead')}
                                                        </Button>
                                                        <div className="relative">
                                                            <div className="absolute inset-0 flex items-center"><span className="w-full border-t" /></div>
                                                            <div className="relative flex justify-center text-[10px] uppercase"><span className="bg-background px-2 text-muted-foreground font-bold">{t('Or')}</span></div>
                                                        </div>
                                                        <Button 
                                                            size="sm" 
                                                            variant="outline" 
                                                            className="w-full text-[11px] h-7 border-dashed"
                                                            onClick={() => handleAddAsLead()}
                                                        >
                                                            {t('Create New Lead Anyway')}
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <>
                                                        <p className="text-[11px] text-muted-foreground mb-3 text-center">
                                                            {t('This contact is not yet linked to a Lead or Contact record.')}
                                                        </p>
                                                        <Button 
                                                            size="sm" 
                                                            variant="outline" 
                                                            className="w-full text-[11px] h-7"
                                                            onClick={() => handleAddAsLead()}
                                                        >
                                                            {t('Add as Lead')}
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        )}
                                        
                                        {/* Activities */}
                                        <div>
                                            <h4 className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-2">
                                                {t('Activities')}
                                            </h4>
                                            <div className="space-y-2">
                                                <div className="flex gap-2 items-start">
                                                    <div className="h-1.5 w-1.5 rounded-full bg-green-500 mt-1.5 shrink-0 shadow-[0_0_6px_rgba(34,197,94,0.4)]" />
                                                    <p className="text-[11px]">
                                                        <span className="font-semibold">{t('Last Contact')}:</span>{' '}
                                                        {timeAgo(selectedThread.last_message_at) || t('N/A')}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2 items-start">
                                                    <div className="h-1.5 w-1.5 rounded-full bg-muted mt-1.5 shrink-0" />
                                                    <p className="text-[11px]">
                                                        <span className="font-semibold">{t('Created')}:</span>{' '}
                                                        {timeAgo(selectedThread.created_at) || t('N/A')}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                            <Label htmlFor="compose-to" className="w-[72px] text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('To')}</Label>
                            <Input 
                                id="compose-to"
                                value={composeTo}
                                onChange={(e) => setComposeTo(e.target.value)}
                                placeholder="recipient@example.com"
                                className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto break-all bg-transparent text-sm"
                            />
                        </div>
                        <div className="flex items-center px-6 py-3 border-b focus-within:bg-muted/30 transition-colors group">
                            <Label htmlFor="compose-subject" className="w-[72px] text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('Subject')}</Label>
                            <Input 
                                id="compose-subject"
                                value={composeSubject}
                                onChange={(e) => setComposeSubject(e.target.value)}
                                placeholder={t('Enter subject here...')}
                                className="flex-1 border-0 shadow-none focus-visible:ring-0 px-0 h-auto font-semibold bg-transparent text-sm"
                            />
                        </div>
                        <div className="flex flex-col relative focus-within:bg-muted/10 transition-colors duration-300">
                            <textarea 
                                value={composeBody}
                                onChange={(e) => setComposeBody(e.target.value)}
                                placeholder={t('Write your message here...')}
                                className="w-full min-h-[250px] p-6 text-sm bg-transparent border-0 focus:ring-0 resize-none outline-none leading-relaxed"
                            />
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
                            {/* Toolbar */}
                            <div className="flex items-center gap-1 px-5 py-2 border-t bg-background relative z-10 shadow-[0_-4px_10px_rgba(0,0,0,0.02)]">
                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors"><Type className="h-4 w-4" /></Button>
                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors"><Link className="h-4 w-4" /></Button>
                                <div className="w-px h-5 bg-border mx-2" />
                                <input type="file" multiple ref={composeFileRef} className="hidden" onChange={(e) => { if (e.target.files) setComposeFiles(prev => [...prev, ...Array.from(e.target.files!)]); e.target.value = ''; }} />
                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors" onClick={() => composeFileRef.current?.click()}><Paperclip className="h-4 w-4" /></Button>
                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors" onClick={() => composeFileRef.current?.click()}><ImageIcon className="h-4 w-4" /></Button>
                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/50 rounded-full transition-colors"><Smile className="h-4 w-4" /></Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter className="px-5 py-4 bg-muted/10 sm:justify-between items-center rounded-b-xl">
                        <Button variant="ghost" size="icon" className="h-9 w-9 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-full transition-colors" onClick={() => setShowCompose(false)} disabled={isComposing}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
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

        </PageTemplate>
    );
}
