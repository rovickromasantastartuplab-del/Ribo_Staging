import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ArrowLeft, User, Calendar, Reply, MoreHorizontal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';

interface EmailMessage {
    id: number;
    gmail_message_id: string;
    from_email: string;
    from_name: string | null;
    to_emails: string[];
    cc_emails: string[] | null;
    subject: string | null;
    body_html: string | null;
    body_preview: string | null;
    sent_at: string;
}

interface EmailThread {
    id: number;
    gmail_thread_id: string;
    subject: string;
    participants: string[];
}

interface Props {
    thread: EmailThread;
    messages: EmailMessage[];
    gmailAccount: {
        gmail_address: string;
    };
}

export default function GmailShow({ thread, messages, gmailAccount }: Props) {
    const { t } = useTranslation();

    // Utility to determine if a message is sent or received by the connected user
    const isSentByMe = (email: string) => {
        return email.toLowerCase() === gmailAccount.gmail_address.toLowerCase();
    };

    return (
        <PageTemplate 
            title={t('Email Thread')}
            description={thread.subject || t('Viewing email conversation')}
            url={`/gmail/threads/${thread.gmail_thread_id}`}
            breadcrumbs={[
                { title: t('Gmail Inbox'), href: route('gmail.threads') },
                { title: t('Thread') }
            ]}
        >
            <Head title={thread.subject || t('Email Thread')} />

            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Link href={route('gmail.threads')}>
                        <Button variant="outline" size="icon" className="h-9 w-9">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <h2 className="text-2xl font-bold tracking-tight truncate max-w-3xl">
                        {thread.subject || t('(No Subject)')}
                    </h2>
                </div>
            </div>

            <div className="space-y-6">
                {messages.map((message, index) => {
                    const sentByMe = isSentByMe(message.from_email);
                    const isLatest = index === messages.length - 1;

                    return (
                        <div 
                            key={message.id} 
                            className={`bg-card border rounded-xl overflow-hidden shadow-sm ${isLatest ? 'ring-1 ring-primary/20' : ''}`}
                        >
                            {/* Message Header */}
                            <div className={`p-4 border-b flex items-start justify-between ${sentByMe ? 'bg-muted/30' : 'bg-background'}`}>
                                <div className="flex items-start gap-4">
                                    <div className={`h-10 w-10 rounded-full flex items-center justify-center text-white ${sentByMe ? 'bg-blue-600' : 'bg-slate-600'}`}>
                                        <User className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-foreground">
                                                {message.from_name || message.from_email}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                &lt;{message.from_email}&gt;
                                            </span>
                                        </div>
                                        <div className="text-sm text-muted-foreground mt-0.5">
                                            {t('To')}: {message.to_emails.join(', ')}
                                            {message.cc_emails && message.cc_emails.length > 0 && (
                                                <span className="ml-2">
                                                    {t('Cc')}: {message.cc_emails.join(', ')}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-sm text-muted-foreground flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    {format(new Date(message.sent_at), 'MMM d, yyyy h:mm a')}
                                </div>
                            </div>

                            {/* Message Body */}
                            <div className="p-6 bg-background">
                                {message.body_html ? (
                                    <div 
                                        className="prose prose-sm max-w-none dark:prose-invert"
                                        dangerouslySetInnerHTML={{ __html: message.body_html }}
                                        style={{ 
                                            // Optional: Scoped styling to prevent email styles from breaking the layout
                                            wordBreak: 'break-word',
                                            overflowWrap: 'break-word',
                                        }}
                                    />
                                ) : (
                                    <div className="whitespace-pre-wrap text-sm text-foreground font-sans">
                                        {message.body_preview || t('No content.')}
                                    </div>
                                )}
                            </div>
                            
                            {/* Message Actions (Placeholder for future reply functionality) */}
                            {isLatest && (
                                <div className="p-3 border-t bg-muted/10 flex items-center gap-2">
                                    <Button variant="outline" size="sm" className="flex items-center gap-1 opacity-50 cursor-not-allowed text-muted-foreground" title="Replying will be available in Phase 2">
                                        <Reply className="h-4 w-4" /> {t('Reply')}
                                    </Button>
                                    <Button variant="ghost" size="sm" className="opacity-50 cursor-not-allowed">
                                        <MoreHorizontal className="h-4 w-4" />
                                    </Button>
                                    <span className="text-xs text-muted-foreground ml-auto">{t('Replying directly from CRM coming in Phase 2')}</span>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </PageTemplate>
    );
}
