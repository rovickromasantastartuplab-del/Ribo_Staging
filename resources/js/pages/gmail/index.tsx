import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Mail, Search, RefreshCw, AlertCircle, ChevronRight, Inbox } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatDistanceToNow } from 'date-fns';

interface EmailThread {
    id: number;
    gmail_thread_id: string;
    subject: string;
    snippet: string;
    participants: string[];
    message_count: number;
    last_message_at: string;
    is_read: boolean;
}

interface PaginationData {
    data: EmailThread[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface Props {
    threads: PaginationData | never[];
    gmailAccount: {
        id: number;
        gmail_address: string;
        last_sync_at: string | null;
        sync_status: string;
        sync_error: string | null;
    } | null;
    filters: {
        search: string | null;
    };
    isOwner: boolean;
}

export default function GmailIndex({ threads, gmailAccount, filters, isOwner }: Props) {
    const { t } = useTranslation();
    const [searchQuery, setSearchQuery] = useState(filters.search || '');

    const { post, processing } = useForm({});

    const handleSync = () => {
        post(route('settings.gmail.sync'), {
            preserveScroll: true,
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        window.location.href = route('gmail.threads', { search: searchQuery });
    };

    return (
        <PageTemplate 
            title={t('Gmail Inbox')}
            description={t('View and manage your synced email threads')}
            url="/gmail/threads"
        >
            <Head title={t('Gmail Inbox')} />

            <div className="flex justify-between items-center mb-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">{t('Gmail Inbox')}</h2>
                    <p className="text-muted-foreground">
                        {gmailAccount 
                            ? t('Viewing emails synced from') + ` ${gmailAccount.gmail_address}` 
                            : t('Connect your Gmail account in Integrations Settings')}
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    {gmailAccount ? (
                        <>
                            <div className="text-sm text-muted-foreground mr-2">
                                {gmailAccount.sync_status === 'syncing' ? (
                                    <span className="flex items-center gap-1 text-primary">
                                        <RefreshCw className="h-3 w-3 animate-spin" /> {t('Syncing...')}
                                    </span>
                                ) : (
                                    <span>
                                        {t('Last sync')}: {gmailAccount.last_sync_at ? formatDistanceToNow(new Date(gmailAccount.last_sync_at), { addSuffix: true }) : t('Never')}
                                    </span>
                                )}
                            </div>
                            <Button 
                                variant="outline" 
                                onClick={handleSync} 
                                disabled={processing || gmailAccount.sync_status === 'syncing'}
                                className="flex items-center gap-2"
                            >
                                <RefreshCw className={`h-4 w-4 ${gmailAccount.sync_status === 'syncing' ? 'animate-spin' : ''}`} />
                                {t('Sync Now')}
                            </Button>
                        </>
                    ) : isOwner && (
                        <Link href="/settings#integrations-settings">
                            <Button>{t('Connect Gmail')}</Button>
                        </Link>
                    )}
                </div>
            </div>

            {gmailAccount && gmailAccount.sync_error && (
                <div className="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md shadow-sm">
                    <div className="flex items-start">
                        <AlertCircle className="h-5 w-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" />
                        <div>
                            <h3 className="text-red-800 font-medium">{t('Sync Error')}</h3>
                            <p className="text-red-700 text-sm mt-1">{gmailAccount.sync_error}</p>
                            <Link href="/settings#integrations-settings" className="text-red-800 text-sm font-medium hover:underline mt-2 inline-block">
                                {t('Go to Settings to Reconnect')}
                            </Link>
                        </div>
                    </div>
                </div>
            )}

            <Card className="shadow-sm">
                <CardHeader className="p-4 border-b flex flex-row items-center justify-between bg-muted/20">
                    <form onSubmit={handleSearch} className="relative w-full max-w-sm flex items-center">
                        <Search className="absolute left-3 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder={t('Search emails or participants...')}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9 bg-background w-full"
                        />
                    </form>
                    
                    {/* Placeholder for pagination info, could go here */}
                </CardHeader>

                <CardContent className="p-0">
                    {!gmailAccount ? (
                        <div className="flex flex-col items-center justify-center p-12 text-center">
                            <div className="h-16 w-16 bg-muted rounded-full flex items-center justify-center mb-4">
                                <Mail className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-medium">{t('No Gmail Account Connected')}</h3>
                            <p className="text-muted-foreground max-w-md mt-2 mb-6">
                                {t('Connect your Gmail account to sync emails, view them alongside your leads, and keep your communication in one place.')}
                            </p>
                            {isOwner && (
                                <Link href="/settings#integrations-settings">
                                    <Button>{t('Go to Settings')}</Button>
                                </Link>
                            )}
                        </div>
                    ) : (threads.data && threads.data.length > 0) ? (
                        <div className="divide-y">
                            {threads.data.map((thread) => (
                                <Link 
                                    key={thread.id} 
                                    href={route('gmail.thread.show', thread.gmail_thread_id)}
                                    className={`flex items-start p-4 hover:bg-muted/50 transition-colors ${!thread.is_read ? 'bg-blue-50/30' : ''}`}
                                >
                                    <div className="flex-shrink-0 mr-4 mt-1">
                                        <div className={`h-8 w-8 rounded-full flex items-center justify-center ${!thread.is_read ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}>
                                            {thread.participants && thread.participants.length > 0 
                                                ? thread.participants[0].charAt(0).toUpperCase() 
                                                : <Mail className="h-4 w-4" />}
                                        </div>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between mb-1">
                                            <p className={`text-sm truncate mr-4 ${!thread.is_read ? 'font-bold text-foreground' : 'font-medium text-foreground'}`}>
                                                {thread.participants ? thread.participants.join(', ') : t('Unknown Participants')}
                                                {thread.message_count > 1 && (
                                                    <span className="ml-2 text-xs text-muted-foreground font-normal">
                                                        ({thread.message_count})
                                                    </span>
                                                )}
                                            </p>
                                            <p className={`text-xs whitespace-nowrap ${!thread.is_read ? 'font-bold text-primary' : 'text-muted-foreground'}`}>
                                                {formatDistanceToNow(new Date(thread.last_message_at), { addSuffix: true })}
                                            </p>
                                        </div>
                                        <p className={`text-sm truncate mb-0.5 ${!thread.is_read ? 'font-semibold text-foreground' : 'text-foreground'}`}>
                                            {thread.subject || t('(No Subject)')}
                                        </p>
                                        <p className="text-sm text-muted-foreground truncate line-clamp-1">
                                            {thread.snippet || t('No content preview available.')}
                                        </p>
                                    </div>
                                    <div className="ml-4 flex-shrink-0 flex items-center h-full">
                                        <ChevronRight className="h-5 w-5 text-muted-foreground opacity-50" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center p-12 text-center">
                            <div className="h-16 w-16 bg-muted rounded-full flex items-center justify-center mb-4">
                                <Inbox className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-medium">{t('Your inbox is empty')}</h3>
                            <p className="text-muted-foreground max-w-md mt-2 mb-6">
                                {searchQuery 
                                    ? t('No emails matched your search query.')
                                    : t('We couldn\'t find any synced emails. If you just connected, it might take a moment to sync or try clicking Sync Now.')}
                            </p>
                            {searchQuery && (
                                <Button variant="outline" onClick={() => setSearchQuery('')}>
                                    {t('Clear Search')}
                                </Button>
                            )}
                        </div>
                    )}
                </CardContent>
                
                {/* Pagination */}
                {threads && 'data' in threads && threads.last_page > 1 && (
                    <div className="p-4 border-t flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                            {t('Showing')} {threads.data.length} {t('of')} {threads.total} {t('threads')}
                        </span>
                        <div className="flex gap-2">
                            <Link href={threads.prev_page_url || '#'} preserveScroll>
                                <Button variant="outline" size="sm" disabled={!threads.prev_page_url}>
                                    {t('Previous')}
                                </Button>
                            </Link>
                            <Link href={threads.next_page_url || '#'} preserveScroll>
                                <Button variant="outline" size="sm" disabled={!threads.next_page_url}>
                                    {t('Next')}
                                </Button>
                            </Link>
                        </div>
                    </div>
                )}
            </Card>
        </PageTemplate>
    );
}
