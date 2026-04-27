<?php

namespace App\Services\Omnichannel\Drivers;

use App\Contracts\Omnichannel\MailboxProvider;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class SmtpImapProvider implements MailboxProvider
{
    /**
     * Synchronize inbound messages via IMAP.
     */
    public function syncInbound(ChannelAccount $account): array
    {
        $stats = ['synced' => 0, 'errors' => 0, 'status' => 'success'];

        try {
            $client = $this->getImapClient($account);
            $client->connect();

            $folders = $client->getFolders();
            $inbox = $folders->where('name', 'INBOX')->first();

            if (!$inbox) {
                // Try to find a folder that contains 'INBOX' case-insensitive
                $inbox = $folders->filter(function($f) {
                    return stripos($f->name, 'INBOX') !== false;
                })->first();
            }

            if (!$inbox) {
                return ['synced' => 0, 'errors' => 1, 'status' => 'error', 'message' => 'INBOX folder not found'];
            }

            // Fetch last 100 messages or last 30 days
            $messages = $inbox->query()
                ->since(now()->subDays(30))
                ->limit(100)
                ->get();

            foreach ($messages as $imapMessage) {
                try {
                    $this->processInboundMessage($account, $imapMessage);
                    $stats['synced']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error("Failed to sync IMAP message UID: {$imapMessage->getUid()}", ['error' => $e->getMessage()]);
                }
            }

            // Update last sync time
            $account->update(['last_sync_at' => now(), 'sync_status' => 'success']);

        } catch (\Exception $e) {
            Log::error("IMAP sync failed for account: {$account->email_address}", ['error' => $e->getMessage()]);
            $account->update(['sync_status' => 'error']);
            return ['synced' => 0, 'errors' => 1, 'status' => 'error', 'message' => $e->getMessage()];
        }

        return $stats;
    }

    /**
     * Send an outgoing message via SMTP.
     */
    public function sendOutgoing(EmailMessage $message): bool
    {
        $account = $message->thread ? $message->thread->channelAccount : null;
        if (!$account) {
            // Check if it's a new message (compose) where account is linked later or provided differently
            // In our controllers, we usually link it or pass it.
            return false; 
        }

        try {
            $config = $account->configuration;
            
            // Build dynamic SMTP DSN
            $encryption = $config['smtp_encryption'] ?? 'tls';
            $port = $config['smtp_port'] ?? 587;
            $host = $config['smtp_host'] ?? '';
            $user = urlencode($config['smtp_username'] ?? '');
            $pass = urlencode($config['smtp_password'] ?? '');

            // Construct DSN for Symfony Mailer
            $dsn = "smtp://{$user}:{$pass}@{$host}:{$port}";
            if ($encryption === 'ssl') {
                $dsn = "smtps://{$user}:{$pass}@{$host}:{$port}";
            }

            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from(new Address($account->email_address, $account->user->name ?? 'Shared Inbox'))
                ->to(...($message->to_emails ?? []))
                ->subject($message->subject)
                ->html($message->body_html);

            if (!empty($message->cc_emails)) {
                $email->cc(...$message->cc_emails);
            }

            if (!empty($message->bcc_emails)) {
                $email->bcc(...$message->bcc_emails);
            }

            foreach (($message->metadata['outgoing_attachments'] ?? []) as $attachment) {
                $absolutePath = storage_path('app/' . $attachment['path']);

                if (is_file($absolutePath)) {
                    $email->attachFromPath(
                        $absolutePath,
                        $attachment['name'] ?? basename($absolutePath),
                        $attachment['mime'] ?? null
                    );
                }
            }

            // Threading headers
            $replyToHeader = $message->metadata['reply_to_message_id_header'] ?? null;

            if ($replyToHeader) {
                $email->getHeaders()->addTextHeader('In-Reply-To', $replyToHeader);
                $email->getHeaders()->addTextHeader('References', $replyToHeader);
            }

            if ($message->message_id_header) {
                $email->getHeaders()->addIdHeader('Message-ID', trim($message->message_id_header, '<>'));
            }

            $sentMessage = $mailer->send($email);
            
            // Update message with external ID (Message-ID header produced by Symfony)
            $headerId = $email->getHeaders()->get('Message-ID')?->getBody();
            if ($headerId) {
                $message->update(['external_message_id' => $headerId]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("SMTP send failed for account: {$account->email_address}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Download attachment from the server.
     */
    public function downloadAttachment(EmailMessage $message, string $attachmentId): string
    {
        $account = $message->thread->channelAccount;
        if (!$account) return '';

        try {
            $client = $this->getImapClient($account);
            $client->connect();

            $folders = $client->getFolders();
            $inbox = $folders->where('name', 'INBOX')->first();

            // Find message by UID (stored in external_message_id)
            $imapMessage = $inbox->query()->uid($message->external_message_id)->get()->first();
            if (!$imapMessage) return '';

            $attachment = $imapMessage->getAttachments()->filter(function($a) use ($attachmentId) {
                return (string)$a->part_number === $attachmentId;
            })->first();

            if ($attachment) {
                $tempPath = storage_path('app/temp_attachments/' . uniqid() . '_' . $attachment->name);
                if (!file_exists(dirname($tempPath))) {
                    mkdir(dirname($tempPath), 0755, true);
                }
                $attachment->save(dirname($tempPath), basename($tempPath));
                return $tempPath;
            }

        } catch (\Exception $e) {
            Log::error("IMAP attachment download failed", ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Update thread status/labels.
     */
    public function updateThreadStatus(EmailThread $thread, string $status): bool
    {
        // IMAP standard doesn't have "threads", but we could mark messages as Seen/Unseen
        return true; 
    }

    public function getLiveAttachments(\App\Models\EmailMessage $message): array
    {
        // Extract from metadata stored during sync
        return $message->metadata['attachments'] ?? [];
    }

    /**
     * Resolve IMAP client for the account.
     */
    protected function getImapClient(ChannelAccount $account): \Webklex\PHPIMAP\Client
    {
        $config = $account->configuration;
        
        return \Webklex\IMAP\Facades\Client::make([
            'host'          => $config['imap_host'] ?? '',
            'port'          => $config['imap_port'] ?? 993,
            'encryption'    => $config['imap_encryption'] ?? 'ssl',
            'validate_cert' => true,
            'username'      => $config['imap_username'] ?? '',
            'password'      => $config['imap_password'] ?? '',
            'protocol'      => 'imap'
        ]);
    }

    /**
     * Process a single inbound IMAP message.
     */
    protected function processInboundMessage(ChannelAccount $account, \Webklex\PHPIMAP\Message $msg)
    {
        $uid = $msg->getUid();
        
        // Check if already synced
        if (EmailMessage::where('external_message_id', $uid)->exists()) {
            return;
        }

        $messageId = $msg->getMessageId();
        $inReplyTo = $msg->getInReplyTo();
        $references = $msg->getReferences(); // Usually an array

        // 1. Threading logic
        $thread = $this->findThread($account, $messageId, $inReplyTo, $references, $msg->getSubject());

        // Extract attachment metadata
        $attachments = [];
        foreach ($msg->getAttachments() as $a) {
            $attachments[] = [
                'id' => (string)$a->part_number,
                'name' => $a->name,
                'size' => $a->size,
                'content_type' => $a->content_type,
            ];
        }

        // 2. Create the message
        $newMessage = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'external_message_id' => $uid, // We use UID for unique tracking in IMAP
            'message_id_header' => $messageId,
            'from_email' => $msg->getFrom()[0]->mail ?? '',
            'to_emails' => collect($msg->getTo())->map(fn($t) => $t->mail)->toArray(),
            'cc_emails' => collect($msg->getCc())->map(fn($t) => $t->mail)->toArray(),
            'subject' => $msg->getSubject() ?: '(No Subject)',
            'body_html' => $msg->getHTMLBody() ?: $msg->getTextBody(),
            'is_read' => $msg->getFlags()->has('seen'),
            'metadata' => [
                'attachments' => $attachments
            ],
            'created_at' => $msg->getDate(),
            'created_by' => $account->user->id,
        ]);

        // 3. Update thread's latest activity
        $thread->update(['last_message_at' => $newMessage->created_at]);
    }

    /**
     * Find existing thread based on Message-ID headers or create a new one.
     */
    protected function findThread(ChannelAccount $account, $messageId, $inReplyTo, $references, $subject): EmailThread
    {
        // Try to find parent by In-Reply-To
        if ($inReplyTo) {
            $parent = EmailMessage::where('message_id_header', $inReplyTo)->first();
            if ($parent && $parent->thread) {
                return $parent->thread;
            }
        }

        // Try to find any match in References
        if ($references && is_array($references)) {
            $parents = EmailMessage::whereIn('message_id_header', $references)->get();
            foreach ($parents as $p) {
                if ($p->thread) return $p->thread;
            }
        }

        // Fallback: search by subject and similar participants (Simulated threading)
        // For now, if no header match, create new.
        
        return EmailThread::create([
            'channel_account_id' => $account->id,
            'external_thread_id' => $messageId, // Using root Message-ID as Thread ID for IMAP
            'subject' => $subject ?: '(No Subject)',
            'status' => 'Open',
            'created_by' => $account->user_id ?? 1,
        ]);
    }
}
