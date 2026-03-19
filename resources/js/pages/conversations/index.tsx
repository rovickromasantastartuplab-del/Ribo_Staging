import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { 
    Inbox, 
    Send, 
    Archive, 
    Search, 
    Filter, 
    MoreVertical, 
    User,
    CheckCircle2,
    Clock,
    UserPlus,
    X,
    RefreshCw,
    AlertCircle
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { toast } from '@/components/custom-toast';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { getEcho } from '@/utils/echo';
import { sanitizeHtml } from '@/utils/sanitize-html';

// Sub-components
const SidebarItem = ({ icon: Icon, label, active, onClick, count }: any) => (
    <button
        onClick={onClick}
        className={`w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors ${
            active ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-muted text-muted-foreground'
        }`}
    >
        <div className="flex items-center gap-3">
            <Icon className="h-4 w-4" />
            <span>{label}</span>
        </div>
        {count > 0 && (
            <span className={`text-[10px] px-1.5 py-0.5 rounded-full ${
                active ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20 text-muted-foreground'
            }`}>
                {count}
            </span>
        )}
    </button>
);

export default function ConversationsIndex({ gmailAccount, companyId, isOwner, unreadCount: initialUnreadCount }: { gmailAccount: any, companyId: number, isOwner: boolean, unreadCount?: number }) {
    const { t } = useTranslation();
    const [selectedFolder, setSelectedFolder] = useState('inbox');
    const [threads, setThreads] = useState<any[]>([]);
    const [selectedThread, setSelectedThread] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [showContactSidebar, setShowContactSidebar] = useState(true);
    const [replyBody, setReplyBody] = useState('');
    const [submittingReply, setSubmittingReply] = useState(false);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount || 0);
    const [searchQuery, setSearchQuery] = useState('');

    const { post: inertiaPost } = useForm({});

    // Keep track of the currently selected thread so the Pusher callback can access it
    // without needing to include selectedThread in the useEffect dependency array (which would re-attach listeners constantly)
    const selectedThreadIdRef = React.useRef<number | null>(null);
    useEffect(() => {
        selectedThreadIdRef.current = selectedThread?.id || null;
    }, [selectedThread?.id]);

    useEffect(() => {
        fetchThreads(false);

        if (!companyId) return;

        // Initialize Pusher listener for real-time updates securely
        const channel = getEcho().private(`company.${companyId}`)
            .listen('.gmail.sync.completed', (data: any) => {
                console.log('Real-time sync completed:', data, 'Current Auth GmailAccountId:', gmailAccount?.id);
                // Only refresh if it's for this account
                // Use loose equality (==) to handle string vs integer mismatch between JS and PHP serialization
                if (gmailAccount && data.gmailAccountId == gmailAccount.id) {
                    fetchThreads(true); // Silent refresh of the sidebar list
                    
                    // If the user currently has a thread open, silently refresh its contents too
                    // so the new message pops in automatically
                    if (selectedThreadIdRef.current) {
                        axios.get(route('api.conversations.show', selectedThreadIdRef.current))
                            .then(response => setSelectedThread(response.data))
                            .catch(err => console.error('Failed to silent-refresh active thread:', err));
                    }
                }
            });

        return () => {
            channel.stopListening('.gmail.sync.completed');
        };
    }, [selectedFolder, gmailAccount?.id, companyId]);

    const fetchThreads = async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const params: any = { folder: selectedFolder };
            if (searchQuery.trim()) params.search = searchQuery.trim();
            const response = await axios.get(route('api.conversations.threads', params));
            const fetchedThreads = response.data.data;
            setThreads(fetchedThreads);
            // BUG-13: Update unread count from fetched data
            const unread = fetchedThreads.filter((t: any) => !t.is_read).length;
            setUnreadCount(unread);
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
            onSuccess: () => {
                setIsSyncing(false);
                fetchThreads();
                // success toast is handled by Laravel session and our global flash listener, 
                // but we can add an extra one here if needed.
            },
            onError: (errors) => {
                setIsSyncing(false);
                console.error('Sync failed:', errors);
                toast.error(t('Failed to synchronize inbox'));
            }
        });
    };

    const handleSelectThread = async (thread: any) => {
        setLoading(true);
        try {
            const response = await axios.get(route('api.conversations.show', thread.id));
            setSelectedThread(response.data);
        } catch (error) {
            console.error('Failed to fetch thread details:', error);
            toast.error(t('Failed to load conversation details'));
        } finally {
            setLoading(false);
        }
    };

    const handleSendReply = async () => {
        if (!replyBody.trim()) return;

        setSubmittingReply(true);
        try {
            const response = await axios.post(route('api.conversations.reply', selectedThread.id), {
                body: replyBody
            });
            
            toast.success(t('Reply sent successfully'));
            setReplyBody('');
            
            // Refresh thread details to show new message (if our backend synced it back)
            handleSelectThread(selectedThread);
        } catch (error: any) {
            console.error('Failed to send reply:', error);
            toast.error(error.response?.data?.error || t('Failed to send reply'));
        } finally {
            setSubmittingReply(false);
        }
    };

    return (
        <PageTemplate 
            title={t('Conversations')}
            description={t('Unified inbox for all relationships')}
            url="/conversations"
        >
            <Head title={t('Conversations Hub')} />

            <div className="flex h-[calc(100vh-180px)] overflow-hidden border rounded-xl bg-background shadow-sm">
                
                {/* Pane 1: Folders (Left Sidebar) */}
                <div className="w-[240px] border-r flex flex-col bg-muted/30">
                    <div className="p-4 flex-1">
                        <div className="flex items-center justify-between mb-4 px-2">
                            <h2 className="text-lg font-semibold">{t('Conversations')}</h2>
                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={handleSync} disabled={isSyncing}>
                                <RefreshCw className={`h-4 w-4 ${isSyncing ? 'animate-spin text-primary' : ''}`} />
                            </Button>
                        </div>
                        <div className="space-y-1">
                            <SidebarItem 
                                icon={Inbox} 
                                label={t('Inbox')} 
                                active={selectedFolder === 'inbox'} 
                                onClick={() => setSelectedFolder('inbox')}
                                count={unreadCount}
                            />
                            <SidebarItem 
                                icon={Archive} 
                                label={t('Unassigned')} 
                                active={selectedFolder === 'unassigned'} 
                                onClick={() => setSelectedFolder('unassigned')}
                            />
                            <SidebarItem 
                                icon={Send} 
                                label={t('Sent')} 
                                active={selectedFolder === 'sent'} 
                                onClick={() => setSelectedFolder('sent')}
                            />
                        </div>
                    </div>
                </div>

                {/* Pane 2: Thread List */}
                <div className="w-[380px] border-r flex flex-col bg-background">
                    <div className="p-4 border-b">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input 
                                placeholder={t('Search threads...')} 
                                className="pl-9 bg-muted/50 border-none h-9 focus-visible:ring-1"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') fetchThreads(); }}
                            />
                        </div>
                    </div>
                    <ScrollArea className="flex-1">
                        {!gmailAccount ? (
                            <div className="flex flex-col items-center justify-center h-full text-center px-6 py-12">
                                <div className="h-16 w-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
                                    <AlertCircle className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="text-lg font-semibold mb-2">{t('Email Not Connected')}</h3>
                                <p className="text-sm text-muted-foreground mb-6 max-w-[240px]">
                                    {isOwner 
                                        ? t('Connect your Gmail account in settings to start managing conversations.')
                                        : t('Please ask your Company Owner to connect a Gmail account in settings.')}
                                </p>
                                {isOwner && (
                                    <Button onClick={() => window.location.href = route('settings', ['#integrations-settings'])}>
                                        {t('Connect Gmail')}
                                    </Button>
                                )}
                            </div>
                        ) : gmailAccount?.sync_status === 'error' && threads.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-full text-center px-6 py-12">
                                <AlertCircle className="h-12 w-12 text-destructive mb-4" />
                                <h3 className="text-lg font-semibold mb-2">{t('Synchronization Error')}</h3>
                                <p className="text-sm text-muted-foreground mb-6 max-w-[240px]">
                                    {gmailAccount.sync_error || t('An error occurred while syncing your emails. Please try reconnecting.')}
                                </p>
                                <Button variant="outline" onClick={handleSync} disabled={isSyncing}>
                                    {isSyncing ? <RefreshCw className="h-4 w-4 animate-spin mr-2" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                                    {t('Try Again')}
                                </Button>
                                <Button variant="link" size="sm" className="mt-2" onClick={() => window.location.href = route('settings', ['#integrations-settings'])}>
                                    {t('Go to Integration Settings')}
                                </Button>
                            </div>
                        ) : threads.length > 0 ? (
                            <div className="divide-y text-foreground/90">
                                {threads.map((thread) => (
                                    <button
                                        key={thread.id}
                                        onClick={() => handleSelectThread(thread)}
                                        className={`w-full text-left p-4 hover:bg-muted/50 transition-colors relative flex items-start gap-3 ${
                                            selectedThread?.id === thread.id ? 'bg-primary/5 border-l-2 border-primary' : ''
                                        }`}
                                    >
                                        <Avatar className="h-10 w-10 border border-primary/10 shadow-sm">
                                            <AvatarFallback className="bg-primary/5 text-primary text-xs font-bold">
                                                {(thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0])?.charAt(0).toUpperCase() || 'U'}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex justify-between items-center mb-0.5">
                                                <span className={`text-sm truncate transition-colors ${
                                                    !thread.is_read ? 'font-extrabold' : 'font-bold'
                                                } ${
                                                    selectedThread?.id === thread.id ? 'text-primary' : 'text-foreground'
                                                }`}>
                                                    {thread.leads?.[0]?.name || thread.contacts?.[0]?.name || thread.participants?.find((p: string) => p !== gmailAccount?.email) || thread.participants?.[0] || 'Unknown'}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground/80 font-medium">
                                                    {thread.last_message_at ? formatDistanceToNow(new Date(thread.last_message_at), { addSuffix: false }) : ''}
                                                </span>
                                            </div>
                                            <div className={`text-sm truncate mb-1 text-foreground/90 ${!thread.is_read ? 'font-bold' : 'font-semibold'}`}>
                                                {thread.subject || t('(No Subject)')}
                                            </div>
                                            <div className="text-xs text-muted-foreground/80 truncate line-clamp-1">
                                                {thread.snippet}
                                            </div>
                                            
                                            {/* Entity Badge */}
                                            <div className="mt-2 flex gap-1">
                                                {thread.leads?.length > 0 && (
                                                    <Badge variant="outline" className="text-[10px] bg-blue-50/50 text-blue-700 border-blue-100 font-bold px-1.5 py-0">
                                                        {t('Lead')}
                                                    </Badge>
                                                )}
                                                {thread.contacts?.length > 0 && (
                                                    <Badge variant="outline" className="text-[10px] bg-green-50/50 text-green-700 border-green-100 font-bold px-1.5 py-0">
                                                        {t('Contact')}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center h-full text-center px-6 py-12">
                                <div className="h-16 w-16 bg-muted/50 rounded-full flex items-center justify-center mb-4">
                                    <Inbox className="h-8 w-8 text-muted-foreground/30" />
                                </div>
                                <h3 className="text-base font-semibold mb-1">{t('No conversations found')}</h3>
                                <p className="text-sm text-muted-foreground mb-6 max-w-[200px]">
                                    {gmailAccount?.sync_status === 'syncing' 
                                        ? t('We are currently syncing your inbox...')
                                        : t('Try clicking the sync button to fetch your latest emails.')}
                                </p>
                                {gmailAccount?.sync_error && (
                                    <div className="mx-4 p-3 bg-destructive/5 text-destructive border border-destructive/10 rounded-lg text-xs mb-4">
                                        <span className="font-bold block mb-1 uppercase tracking-tight">{t('Sync Error')}:</span>
                                        {gmailAccount.sync_error}
                                    </div>
                                )}
                                <Button variant="outline" size="sm" onClick={handleSync} disabled={isSyncing}>
                                    <RefreshCw className={`h-3.5 w-3.5 mr-2 ${isSyncing ? 'animate-spin' : ''}`} />
                                    {t('Sync Now')}
                                </Button>
                            </div>
                        )}
                    </ScrollArea>
                </div>

                {/* Pane 3: Thread Details & Reply */}
                <div className="flex-1 flex flex-col min-w-0 bg-muted/5">
                    {selectedThread ? (
                        <>
                            {/* Header */}
                            <div className="h-16 border-b flex items-center justify-between px-6 bg-background">
                                <div className="min-w-0 flex-1">
                                    <h2 className="text-base font-semibold truncate text-foreground/90">
                                        {selectedThread.subject || t('(No Subject)')}
                                    </h2>
                                    <div className="text-xs text-muted-foreground flex items-center gap-2">
                                        <span>{selectedThread.participants?.join(', ')}</span>
                                        <span>•</span>
                                        <span>{selectedThread.message_count} {t('messages')}</span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 ml-4">
                                    <Button variant="ghost" size="icon" onClick={() => setShowContactSidebar(!showContactSidebar)}>
                                        <User className={`h-4 w-4 ${showContactSidebar ? 'text-primary' : ''}`} />
                                    </Button>
                                    <Button variant="ghost" size="icon">
                                        <Archive className="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon">
                                        <MoreVertical className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            {/* Messages Scroll Area */}
                            <ScrollArea className="flex-1 p-6">
                                <div className="max-w-4xl mx-auto space-y-8">
                                    {selectedThread.messages?.map((msg: any) => (
                                        <div key={msg.id} className="flex gap-4">
                                            <Avatar className="h-9 w-9 border">
                                                <AvatarFallback className="bg-muted text-xs">
                                                    {msg.from_email?.charAt(0).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-semibold">{msg.from_email}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {msg.sent_at ? formatDistanceToNow(new Date(msg.sent_at), { addSuffix: true }) : ''}
                                                    </span>
                                                </div>
                                                <div className="bg-background border rounded-lg p-4 shadow-sm text-sm leading-relaxed whitespace-pre-wrap">
                                                    {msg.body_html ? (
                                                        <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(msg.body_html) }} />
                                                    ) : (
                                                        msg.snippet
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>

                            {/* Reply Box */}
                            <div className="p-4 border-t bg-background">
                                <div className="max-w-4xl mx-auto border rounded-xl shadow-sm focus-within:ring-1 focus-within:ring-primary/30 transition-shadow overflow-hidden">
                                    <textarea 
                                        className="w-full min-h-[120px] p-4 text-sm bg-transparent border-none focus:ring-0 resize-none"
                                        placeholder={t('Write your reply here...')}
                                        value={replyBody}
                                        onChange={(e) => setReplyBody(e.target.value)}
                                        disabled={submittingReply}
                                    />
                                    <div className="flex items-center justify-between px-4 py-2 bg-muted/20 border-t">
                                        <div className="flex gap-2">
                                            {/* Formatting options placeholder */}
                                        </div>
                                        <Button 
                                            size="sm" 
                                            className="gap-2 px-6" 
                                            onClick={handleSendReply} 
                                            disabled={submittingReply || !replyBody.trim()}
                                        >
                                            {submittingReply ? (
                                                <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                                            ) : (
                                                <Send className="h-3.5 w-3.5" />
                                            )}
                                            {submittingReply ? t('Sending...') : t('Send Reply')}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center text-center p-12">
                            <div className="h-20 w-20 bg-muted/50 rounded-full flex items-center justify-center mb-6">
                                <Inbox className="h-10 w-10 text-muted-foreground/30" />
                            </div>
                            <h3 className="text-xl font-semibold mb-2">{t('Select a conversation')}</h3>
                            <p className="text-muted-foreground max-w-sm">
                                {t('Choose a thread from the list to view the full message history and CRM details.')}
                            </p>
                        </div>
                    )}
                </div>

                {/* Pane 4: Contact/Lead Details (Right Drawer) */}
                {selectedThread && showContactSidebar && (
                    <div className="w-[300px] border-l flex flex-col bg-background animate-in slide-in-from-right-1 transition-all duration-300">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <h3 className="font-semibold">{t('Contact Details')}</h3>
                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setShowContactSidebar(false)}>
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>

                            {/* Profile Info */}
                            <div className="flex flex-col items-center text-center mb-8">
                                <Avatar className="h-20 w-20 mb-4 border-2 border-primary/10">
                                    <AvatarFallback className="text-xl font-bold bg-primary/5 text-primary">
                                        {(selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || selectedThread.participants?.[0])?.charAt(0).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <h4 className="font-bold text-lg">{selectedThread.leads?.[0]?.name || selectedThread.contacts?.[0]?.name || selectedThread.participants?.[0]}</h4>
                                <p className="text-xs text-muted-foreground">{selectedThread.participants?.find((p: string) => p !== gmailAccount?.email) || selectedThread.participants?.[0]}</p>
                            </div>

                            {/* CRM Context */}
                            <div className="space-y-6">
                                {selectedThread.leads?.length > 0 ? (
                                    <div className="p-4 bg-blue-50/50 border border-blue-100 rounded-lg">
                                        <div className="flex items-center gap-2 mb-3">
                                            <Badge className="bg-blue-600 font-bold">{t('LEAD')}</Badge>
                                            <span className="text-[10px] text-blue-600 uppercase font-bold tracking-wider">{selectedThread.leads[0].lead_status?.name || selectedThread.leads[0].status || t('Active')}</span>
                                        </div>
                                        <div className="space-y-2">
                                            <div className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">{t('Value')}:</span>
                                                <span className="font-semibold">{selectedThread.leads[0].value ? `$${selectedThread.leads[0].value}` : t('N/A')}</span>
                                            </div>
                                            <div className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">{t('Status')}:</span>
                                                <Badge variant="outline" className="h-5 text-[10px]">{selectedThread.leads[0].lead_status?.name || selectedThread.leads[0].status || t('New')}</Badge>
                                            </div>
                                        </div>
                                        <a href={route('leads.show', selectedThread.leads[0].id)}>
                                            <Button variant="link" size="sm" className="p-0 h-auto mt-4 text-xs font-semibold">
                                                {t('View Full Lead Record')}
                                            </Button>
                                        </a>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center p-6 border border-dashed rounded-lg">
                                        <UserPlus className="h-6 w-6 text-muted-foreground/30 mb-2" />
                                        <p className="text-xs text-muted-foreground mb-4 text-center">
                                            {t('This contact is not yet linked to a Lead or Contact record.')}
                                        </p>
                                        <Button size="sm" variant="outline" className="w-full text-xs">
                                            {t('Add as Lead')}
                                        </Button>
                                    </div>
                                )}
                                
                                <div className="space-y-4">
                                    <h4 className="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                        {t('Activities')}
                                    </h4>
                                    <div className="space-y-3">
                                        <div className="flex gap-3">
                                            <div className="h-2 w-2 rounded-full bg-green-500 mt-1.5 shadow-[0_0_8px_rgba(34,197,94,0.4)]" />
                                            <p className="text-xs">
                                                <span className="font-semibold">{t('Last Contact')}:</span> {selectedThread.last_message_at ? formatDistanceToNow(new Date(selectedThread.last_message_at), { addSuffix: true }) : t('N/A')}
                                            </p>
                                        </div>
                                        <div className="flex gap-3">
                                            <div className="h-2 w-2 rounded-full bg-muted mt-1.5" />
                                            <p className="text-xs">
                                                <span className="font-semibold">{t('Created')}:</span> {selectedThread.created_at ? formatDistanceToNow(new Date(selectedThread.created_at), { addSuffix: true }) : t('N/A')}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}
