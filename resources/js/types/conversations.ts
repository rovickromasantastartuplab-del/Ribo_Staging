export interface GmailAccount {
    id: number;
    email: string;
    last_sync_at: string | null;
    sync_status: 'synced' | 'syncing' | 'error' | null;
    sync_error: string | null;
}

export interface EmailMessage {
    id: number;
    email_thread_id: number;
    gmail_message_id: string;
    from_email: string;
    from_name: string | null;
    to_emails: string[];
    cc_emails: string[];
    subject: string;
    body_preview: string;
    body_html: string | null;
    sent_at: string;
    gmail_labels: string[];
}

export interface EmailThread {
    id: number;
    gmail_account_id: number;
    gmail_thread_id: string;
    subject: string;
    snippet: string;
    message_count: number;
    is_read: boolean;
    last_message_at: string;
    participants: string[];
    messages?: EmailMessage[];
    leads?: any[];
    contacts?: any[];
}
