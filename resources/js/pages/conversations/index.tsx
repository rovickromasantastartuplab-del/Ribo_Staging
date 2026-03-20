import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, useForm } from '@inertiajs/react';
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
    PenBox
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { getEcho } from '@/utils/echo';
import { sanitizeHtml } from '@/utils/sanitize-html';

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
        { key: 'inbox',      icon: Inbox,   label: t('Inbox'),      count: unreadCount },
        { key: 'unassigned', icon: Archive, label: t('Unassigned'), count: 0 },
        { key: 'sent',       icon: Send,    label: t('Sent'),       count: 0 },
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
                    { key: 'inbox',      icon: Inbox,   label: t('Inbox'),      count: unreadCount },
                    { key: 'unassigned', icon: Archive, label: t('Unassigned'), count: 0 },
                    { key: 'sent',       icon: Send,    label: t('Sent'),       count: 0 },
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

    const [showCompose, setShowCompose] = useState(false);
    const [composeTo, setComposeTo] = useState('');
    const [composeSubject, setComposeSubject] = useState('');
    const [composeBody, setComposeBody] = useState('');
    const [isComposing, setIsComposing] = useState(false);

    const { post: inertiaPost } = useForm({});

    // Ref for real-time refresh
    const selectedThreadIdRef = React.useRef<number | null>(null);
    useEffect(() => { selectedThreadIdRef.current = selectedThread?.id || null; }, [selectedThread?.id]);

    useEffect(() => {
        fetchThreads(false);
        if (!companyId) return;

        const channel = getEcho().private(`company.${companyId}`)
            .listen('.gmail.sync.completed', (data: any) => {
                if (gmailAccount && data.gmailAccountId == gmailAccount.id) {
                    fetchThreads(true);
                    if (selectedThreadIdRef.current) {
                        axios.get(route('api.conversations.show', selectedThreadIdRef.current))
                            .then(r => setSelectedThread(r.data))
                            .catch(err => console.error('Silent refresh failed:', err));
                    }
                }
            });
        return () => { channel.stopListening('.gmail.sync.completed'); };
    }, [selectedFolder, gmailAccount?.id, companyId]);

    const fetchThreads = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const params: any = { folder: selectedFolder };
            if (searchQuery.trim()) params.search = searchQuery.trim();
            const response = await axios.get(route('api.conversations.threads', params));
            const fetched = response.data.data;
            setThreads(fetched);
            setUnreadCount(fetched.filter((t: any) => !t.is_read).length);
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

    const handleSelectThread = async (thread: any) => {
        setLoading(true);
        try {
            const response = await axios.get(route('api.conversations.show', thread.id));
            setSelectedThread(response.data);
        } catch {
            toast.error(t('Failed to load conversation details'));
        } finally {
            setLoading(false);
        }
    };

    const handleSendReply = async () => {
        if (!replyBody.trim()) return;
        setSubmittingReply(true);
        try {
            await axios.post(route('api.conversations.reply', selectedThread.id), { body: replyBody });
            toast.success(t('Reply sent successfully'));
            setReplyBody('');
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
            await axios.post(route('api.conversations.compose'), {
                to: composeTo,
                subject: composeSubject,
                body: composeBody,
            });
            toast.success(t('Email sent successfully'));
            setShowCompose(false);
            setComposeTo('');
            setComposeSubject('');
            setComposeBody('');
            // The SyncGmailThreadsJob running on the backend will push the new thread via Websockets shortly
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
                                                    <span className={`text-xs truncate flex-1 min-w-0 ${
                                                        !thread.is_read ? 'font-extrabold' : 'font-semibold'
                                                    } ${selectedThread?.id === thread.id ? 'text-primary' : 'text-foreground'}`}>
                                                        {thread.leads?.[0]?.name || thread.contacts?.[0]?.name || thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0] || 'Unknown'}
                                                    </span>
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
                                                {(thread.leads?.length > 0 || thread.contacts?.length > 0) && (
                                                    <div className="mt-1.5 flex gap-1">
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
                                                    </div>
                                                )}
                                            </div>
                                        </button>
                                    ))}
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
                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setShowContactSidebar(!showContactSidebar)}>
                                            <User className={`h-4 w-4 ${showContactSidebar ? 'text-primary' : ''}`} />
                                        </Button>
                                        <Button variant="ghost" size="icon" className="h-8 w-8">
                                            <MoreVertical className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                {/* Messages */}
                                <ScrollArea className="flex-1 min-h-0">
                                    <div className="p-3 lg:p-4 lg:p-6 space-y-4 lg:space-y-6 max-w-4xl mx-auto">
                                        {selectedThread.messages?.map((msg: any) => (
                                            <div key={msg.id} className="flex gap-2 lg:gap-3">
                                                <Avatar className="h-7 w-7 lg:h-8 lg:w-8 shrink-0 border">
                                                    <AvatarFallback className="bg-muted text-[10px]">
                                                        {msg.from_email?.charAt(0).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0 space-y-1.5">
                                                    <div className="flex items-start justify-between gap-2 overflow-hidden min-w-0">
                                                        <span className="text-xs font-semibold truncate flex-1 min-w-0">{msg.from_name || msg.from_email}</span>
                                                        <span className="text-[10px] text-muted-foreground truncate shrink-0 max-w-[90px] text-right">
                                                            {timeAgo(msg.sent_at)}
                                                        </span>
                                                    </div>
                                                    <div className="bg-background border rounded-lg p-3 shadow-sm text-xs lg:text-sm leading-relaxed overflow-hidden break-words [overflow-wrap:anywhere]">
                                                        {msg.body_html ? (
                                                            <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(msg.body_html) }} />
                                                        ) : (
                                                            <span className="whitespace-pre-wrap">{msg.snippet}</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
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
                                        <div className="flex items-center justify-end px-2.5 py-1.5 bg-muted/20 border-t">
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
                                        <Avatar className="h-16 w-16 mb-3 border-2 border-primary/10 shrink-0">
                                            <AvatarFallback className="text-lg font-bold bg-primary/5 text-primary">
                                                {(selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || selectedThread.participants?.[0])?.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <h4 className="font-bold text-sm truncate w-full">
                                            {selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || selectedThread.participants?.[0]}
                                        </h4>
                                        <p className="text-[11px] text-muted-foreground truncate w-full">
                                            {selectedThread.participants?.find((p: string) => p !== gmailAccount?.email) || selectedThread.participants?.[0]}
                                        </p>
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
                                            <div className="flex flex-col items-center p-4 border border-dashed rounded-lg">
                                                <UserPlus className="h-5 w-5 text-muted-foreground/30 mb-1.5" />
                                                <p className="text-[11px] text-muted-foreground mb-3 text-center">
                                                    {t('This contact is not yet linked to a Lead or Contact record.')}
                                                </p>
                                                <Button size="sm" variant="outline" className="w-full text-[11px] h-7">
                                                    {t('Add as Lead')}
                                                </Button>
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
                <DialogContent className="sm:max-w-[600px] p-0 overflow-hidden gap-0">
                    <DialogHeader className="px-5 py-4 border-b bg-muted/40">
                        <DialogTitle className="text-lg font-semibold">{t('New Message')}</DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col">
                        <div className="flex items-center px-4 py-2.5 border-b focus-within:bg-muted/10 transition-colors">
                            <Label htmlFor="compose-to" className="w-16 text-xs font-medium text-muted-foreground">{t('To:')}</Label>
                            <Input 
                                id="compose-to"
                                value={composeTo}
                                onChange={(e) => setComposeTo(e.target.value)}
                                placeholder="recipient@example.com"
                                className="border-0 shadow-none focus-visible:ring-0 px-0 h-auto break-all"
                            />
                        </div>
                        <div className="flex items-center px-4 py-2.5 border-b focus-within:bg-muted/10 transition-colors">
                            <Label htmlFor="compose-subject" className="w-16 text-xs font-medium text-muted-foreground">{t('Subject:')}</Label>
                            <Input 
                                id="compose-subject"
                                value={composeSubject}
                                onChange={(e) => setComposeSubject(e.target.value)}
                                placeholder={t('Enter subject...')}
                                className="border-0 shadow-none focus-visible:ring-0 px-0 h-auto font-medium"
                            />
                        </div>
                        <div className="p-4">
                            <textarea 
                                value={composeBody}
                                onChange={(e) => setComposeBody(e.target.value)}
                                placeholder={t('Write your message here...')}
                                className="w-full min-h-[200px] text-sm bg-transparent border-0 focus:ring-0 resize-none outline-none leading-relaxed"
                            />
                        </div>
                    </div>
                    <DialogFooter className="px-4 py-3 border-t bg-muted/30 sm:justify-between items-center">
                        <Button variant="ghost" size="sm" onClick={() => setShowCompose(false)} disabled={isComposing}>
                            {t('Cancel')}
                        </Button>
                        <Button size="sm" onClick={handleSendNewEmail} disabled={isComposing || !composeTo.trim() || !composeSubject.trim() || !composeBody.trim()} className="gap-2 px-6">
                            {isComposing ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                            {t('Send')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </PageTemplate>
    );
}
