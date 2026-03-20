<?php

namespace App\Services;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Contact;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GmailService
{
    private GoogleClient $client;
    private Gmail $gmail;
    private GmailAccount $account;

    public function __construct(GmailAccount $account)
    {
        $this->account = $account;

        // Google OAuth credentials are always stored under the superadmin's settings
        // Cache the superadmin ID to avoid repeated DB queries
        $superadminId = Cache::remember('superadmin_id', 3600, function () {
            return \App\Models\User::where('type', 'superadmin')->value('id');
        });

        $clientId = ($superadminId ? getSetting('google_client_id', null, $superadminId) : null)
            ?? config('services.google.client_id');

        $clientSecret = ($superadminId ? getSetting('google_client_secret', null, $superadminId) : null)
            ?? config('services.google.client_secret');

        $this->client = new GoogleClient();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);

        // Google Client expects a token array, not just a bare access_token string
        $this->client->setAccessToken([
            'access_token' => $account->access_token,
            'token_type' => 'Bearer',
            'expires_in' => $account->token_expires_at
                ? max(0, now()->diffInSeconds($account->token_expires_at, false))
                : 3600,
            'created' => $account->updated_at?->timestamp ?? time(),
        ]);

        if ($account->refresh_token) {
            $this->client->setAccessType('offline');
        }

        $this->gmail = new Gmail($this->client);
    }

    /**
     * Refresh the access token if it has expired.
     */
    public function refreshTokenIfNeeded(): bool
    {
        if (!$this->account->isTokenExpired()) {
            return true;
        }

        if (!$this->account->refresh_token) {
            Log::error('Gmail token expired and no refresh token available', [
                'gmail_account_id' => $this->account->id,
            ]);
            return false;
        }

        try {
            $this->client->fetchAccessTokenWithRefreshToken($this->account->refresh_token);
            $newToken = $this->client->getAccessToken();

            if (isset($newToken['error'])) {
                Log::error('Gmail token refresh failed', [
                    'gmail_account_id' => $this->account->id,
                    'error' => $newToken['error'],
                ]);
                return false;
            }

            $updateData = [
                'access_token' => $newToken['access_token'],
                'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ];

            // Google may issue a new refresh token — persist it if so
            if (!empty($newToken['refresh_token'])) {
                $updateData['refresh_token'] = $newToken['refresh_token'];
            }

            $this->account->update($updateData);

            return true;
        } catch (\Exception $e) {
            Log::error('Gmail token refresh exception', [
                'gmail_account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * List Gmail threads for the connected account.
     */
    public function listThreads(int $maxResults = 50, ?string $pageToken = null): array
    {
        $params = [
            'maxResults' => $maxResults,
            'q' => 'in:inbox OR in:sent',
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $response = $this->gmail->users_threads->listUsersThreads('me', $params);

        return [
            'threads' => $response->getThreads() ?? [],
            'nextPageToken' => $response->getNextPageToken(),
            'resultSizeEstimate' => $response->getResultSizeEstimate(),
        ];
    }

    /**
     * Get a full thread with all messages.
     */
    public function getThread(string $threadId): ?Gmail\Thread
    {
        try {
            return $this->gmail->users_threads->get('me', $threadId, [
                'format' => 'full',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Gmail thread', [
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sync threads from Gmail into the CRM database (full sync).
     */
    public function syncThreads(int $maxResults = 50): array
    {
        $stats = ['synced' => 0, 'errors' => 0];

        try {
            $result = $this->listThreads($maxResults);
            $companyId = $this->resolveCompanyId();

            $this->logActivity('sync_started', 'Gmail synchronization started', 'Starting full synchronization of inbox and sent items.');

            // Track the latest historyId from any thread for incremental sync baseline
            $latestHistoryId = null;

            foreach ($result['threads'] as $threadMeta) {
                try {
                    $thread = $this->getThread($threadMeta->getId());
                    if (!$thread) {
                        $stats['errors']++;
                        continue;
                    }

                    // Track the highest historyId
                    $threadHistoryId = $thread->getHistoryId();
                    if ($threadHistoryId && (!$latestHistoryId || $threadHistoryId > $latestHistoryId)) {
                        $latestHistoryId = $threadHistoryId;
                    }

                    $this->syncSingleThread($thread, $companyId);
                    $stats['synced']++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync Gmail thread', [
                        'thread_id' => $threadMeta->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            // Update account sync status and persist the historyId for incremental sync
            $this->account->update([
                'last_sync_at' => now(),
                'sync_status' => 'idle',
                'sync_error' => null,
                'last_history_id' => $latestHistoryId ?? $this->account->last_history_id,
            ]);

            $this->logActivity('sync_completed', 'Gmail synchronization completed', "Successfully synced {$stats['synced']} threads.");

        } catch (\Exception $e) {
            Log::error('Gmail sync failed', [
                'gmail_account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            $this->account->update([
                'sync_status' => 'error',
                'sync_error' => $e->getMessage(),
            ]);

            $this->logActivity('sync_error', 'Gmail synchronization failed', "Error: " . $e->getMessage());

            throw $e;
        }

        return $stats;
    }

    /**
     * Perform incremental sync using Gmail history.list API.
     * Only fetches changes since the last known historyId.
     * Returns ['synced' => N, 'errors' => N] or null if fallback to full sync is needed.
     */
    public function incrementalSync(): ?array
    {
        $startHistoryId = $this->account->last_history_id;
        if (!$startHistoryId) {
            return null; // No baseline — caller should do a full sync
        }

        $stats = ['synced' => 0, 'errors' => 0];

        try {
            $companyId = $this->resolveCompanyId();

            $this->logActivity('sync_started', 'Incremental sync started', 'Checking for recent changes in Gmail since last sync.');

            // Fetch history records since last known historyId
            $response = $this->gmail->users_history->listUsersHistory('me', [
                'startHistoryId' => $startHistoryId,
                'historyTypes' => ['messageAdded', 'messageDeleted'],
            ]);

            $historyRecords = $response->getHistory() ?? [];
            $latestHistoryId = $response->getHistoryId();

            // Collect unique thread IDs that changed
            $changedThreadIds = [];
            foreach ($historyRecords as $historyRecord) {
                $messagesAdded = $historyRecord->getMessagesAdded() ?? [];
                foreach ($messagesAdded as $addedMsg) {
                    $msg = $addedMsg->getMessage();
                    if ($msg && $msg->getThreadId()) {
                        $changedThreadIds[$msg->getThreadId()] = true;
                    }
                }
            }

            // Re-sync only the threads that changed
            foreach (array_keys($changedThreadIds) as $threadId) {
                try {
                    $thread = $this->getThread($threadId);
                    if (!$thread) {
                        $stats['errors']++;
                        continue;
                    }
                    $this->syncSingleThread($thread, $companyId);
                    $stats['synced']++;
                } catch (\Exception $e) {
                    Log::error('Failed to incrementally sync Gmail thread', [
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            // Persist the new historyId
            $this->account->update([
                'last_sync_at' => now(),
                'sync_status' => 'idle',
                'sync_error' => null,
                'last_history_id' => $latestHistoryId,
            ]);

            $this->logActivity('sync_completed', 'Incremental sync completed', "Updated " . count($changedThreadIds) . " threads based on recent Gmail activity.");

            Log::info('Incremental Gmail sync completed', [
                'gmail_account_id' => $this->account->id,
                'threads_changed' => count($changedThreadIds),
                'synced' => $stats['synced'],
            ]);

            return $stats;

        } catch (\Google\Service\Exception $e) {
            // historyId expired or invalid — fall back to full sync
            if ($e->getCode() === 404) {
                Log::warning('Gmail historyId expired, falling back to full sync', [
                    'gmail_account_id' => $this->account->id,
                    'start_history_id' => $startHistoryId,
                ]);
                return null; // Signal caller to use full sync
            }
            throw $e;
        }
    }

    /**
     * Sync a single Gmail thread into the database.
     */
    private function syncSingleThread(Gmail\Thread $thread, int $companyId): void
    {
        $messages = $thread->getMessages() ?? [];
        $firstMessage = $messages[0] ?? null;
        $lastMessage = end($messages) ?: $firstMessage;

        $subject = $this->extractHeader($firstMessage, 'Subject');
        $participants = $this->extractParticipants($messages);

        $emailThread = EmailThread::updateOrCreate(
            [
                'gmail_account_id' => $this->account->id,
                'gmail_thread_id' => $thread->getId(),
            ],
            [
                'subject' => $subject,
                'snippet' => $thread->getSnippet() ?? '',
                'participants' => $participants,
                'message_count' => count($messages),
                'last_message_at' => $lastMessage
                    ? $this->parseMessageDate($lastMessage)
                    : now(),
                'is_read' => !in_array('UNREAD', $this->extractThreadLabels($messages)),
                'labels' => $this->extractThreadLabels($messages),
                'created_by' => $companyId,
            ]
        );

        // Collect all live Gmail message IDs from this thread
        $liveGmailMessageIds = [];
        foreach ($messages as $message) {
            $liveGmailMessageIds[] = $message->getId();
            $this->upsertMessage($emailThread, $message, $companyId);
        }

        // Garbage collection: delete any local messages that Google has removed
        // (e.g. temporary drafts that were replaced by the final sent message)
        if (!empty($liveGmailMessageIds)) {
            $emailThread->messages()
                ->whereNotIn('gmail_message_id', $liveGmailMessageIds)
                ->delete();
        }

        $this->autoLinkThread($emailThread, $companyId);
    }

    /**
     * Get connection status for display.
     */
    public function getConnectionStatus(): array
    {
        return [
            'connected' => true,
            'email' => $this->account->gmail_address,
            'last_sync_at' => $this->account->last_sync_at?->toIso8601String(),
            'sync_status' => $this->account->sync_status,
            'sync_error' => $this->account->sync_error,
            'token_expired' => $this->account->isTokenExpired(),
            'needs_reconnect' => $this->account->needsReconnect(),
        ];
    }

    /**
     * Start watching the user's Gmail inbox for real-time webhooks.
     */
    public function watchInbox(): bool
    {
        try {
            // Priority: Database setting (Superadmin) > config/env
            $superadminId = Cache::remember('superadmin_id', 3600, function () {
                return \App\Models\User::where('type', 'superadmin')->value('id');
            });
            $topicName = ($superadminId ? getSetting('google_gmail_pub_sub_topic', null, $superadminId) : null)
                ?? config('services.google.pubsub_topic') 
                ?? env('GMAIL_PUB_SUB_TOPIC');
            
            if (!$topicName) {
                Log::warning('Cannot watch Gmail inbox: Google Pub/Sub Topic is not configured in Settings or .env', [
                    'gmail_account_id' => $this->account->id,
                ]);
                return false;
            }

            $watchRequest = new \Google\Service\Gmail\WatchRequest();
            $watchRequest->setTopicName($topicName);
            $watchRequest->setLabelIds(['INBOX', 'SENT']);

            $response = $this->gmail->users->watch('me', $watchRequest);

            Log::info('Successfully started watching Gmail inbox', [
                'gmail_account_id' => $this->account->id,
                'email' => $this->account->gmail_address,
                'history_id' => $response->getHistoryId(),
                'expiration' => $response->getExpiration(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to watch Gmail inbox', [
                'gmail_account_id' => $this->account->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send an email message or reply, optionally with file attachments.
     *
     * @param string $to        Primary recipient
     * @param string $subject   Email subject
     * @param string $body      HTML body
     * @param string|null $threadId   Gmail thread ID for threading
     * @param string|null $inReplyTo  Message-ID of the message being replied to
     * @param array  $cc        CC recipient email addresses
     * @param array  $attachments  Array of UploadedFile instances
     */
    public function sendMessage(string $to, string $subject, string $body, ?string $threadId = null, ?string $inReplyTo = null, array $cc = [], array $attachments = []): bool
    {
        try {
            $this->refreshTokenIfNeeded();

            $message = new \Google\Service\Gmail\Message();

            if (!empty($attachments)) {
                $rawMessage = $this->buildMultipartMessage($to, $subject, $body, $inReplyTo, $cc, $attachments);
            } else {
                $rawMessage = $this->buildSimpleMessage($to, $subject, $body, $inReplyTo, $cc);
            }

            // Base64Url encode
            $encodedMessage = strtr(base64_encode($rawMessage), '+/', '-_');
            $message->setRaw($encodedMessage);

            if ($threadId) {
                $message->setThreadId($threadId);
            }

            $this->gmail->users_messages->send('me', $message);

            $this->logActivity('email_sent', 'Email sent to ' . $to, "Subject: {$subject}" . (count($attachments) > 0 ? " (" . count($attachments) . " attachments)" : ""));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Gmail message', [
                'gmail_account_id' => $this->account->id,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Build a simple text/html RFC 2822 message (no attachments).
     */
    private function buildSimpleMessage(string $to, string $subject, string $body, ?string $inReplyTo, array $cc): string
    {
        $rawMessage = "From: {$this->account->gmail_address}\r\n";
        $rawMessage .= "To: {$to}\r\n";

        if (!empty($cc)) {
            $rawMessage .= "Cc: " . implode(', ', $cc) . "\r\n";
        }

        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";

        if ($inReplyTo) {
            $rawMessage .= "In-Reply-To: {$inReplyTo}\r\n";
            $rawMessage .= "References: {$inReplyTo}\r\n";
        }

        $rawMessage .= "\r\n{$body}";

        return $rawMessage;
    }

    /**
     * Build a multipart/mixed RFC 2822 message with file attachments.
     */
    private function buildMultipartMessage(string $to, string $subject, string $body, ?string $inReplyTo, array $cc, array $attachments): string
    {
        $boundary = 'boundary_' . md5(uniqid(mt_rand(), true));

        $rawMessage = "From: {$this->account->gmail_address}\r\n";
        $rawMessage .= "To: {$to}\r\n";

        if (!empty($cc)) {
            $rawMessage .= "Cc: " . implode(', ', $cc) . "\r\n";
        }

        $rawMessage .= "Subject: {$subject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";

        if ($inReplyTo) {
            $rawMessage .= "In-Reply-To: {$inReplyTo}\r\n";
            $rawMessage .= "References: {$inReplyTo}\r\n";
        }

        $rawMessage .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $rawMessage .= "\r\n";

        // HTML body part
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
        $rawMessage .= "\r\n";
        $rawMessage .= chunk_split(base64_encode($body));
        $rawMessage .= "\r\n";

        // Attachment parts
        foreach ($attachments as $file) {
            $filename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';
            $fileData = file_get_contents($file->getRealPath());

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: {$mimeType}; name=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
            $rawMessage .= "\r\n";
            $rawMessage .= chunk_split(base64_encode($fileData));
            $rawMessage .= "\r\n";
        }

        // Closing boundary
        $rawMessage .= "--{$boundary}--\r\n";

        return $rawMessage;
    }

    /**
     * Automatically link an email thread to matching Leads and Contacts
     * by scanning participant email addresses against CRM records.
     */
    public function autoLinkThread(EmailThread $emailThread, int $companyId): void
    {
        $participants = $emailThread->participants ?? [];

        if (empty($participants)) {
            return;
        }

        // Filter out the connected Gmail address itself
        $externalParticipants = array_filter($participants, function ($email) {
            return strtolower($email) !== strtolower($this->account->gmail_address);
        });

        if (empty($externalParticipants)) {
            return;
        }

        // Find matching Leads (scoped to the same company)
        $matchingLeads = Lead::where('created_by', $companyId)
            ->whereIn('email', $externalParticipants)
            ->pluck('id');

        foreach ($matchingLeads as $leadId) {
            try {
                $emailThread->leads()->syncWithoutDetaching([
                    $leadId => ['matched_via' => 'auto']
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to auto-link thread to lead', [
                    'thread_id' => $emailThread->id,
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Find matching Contacts (scoped to the same company)
        $matchingContacts = Contact::where('created_by', $companyId)
            ->whereIn('email', $externalParticipants)
            ->pluck('id');

        foreach ($matchingContacts as $contactId) {
            try {
                $emailThread->contacts()->syncWithoutDetaching([
                    $contactId => ['matched_via' => 'auto']
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to auto-link thread to contact', [
                    'thread_id' => $emailThread->id,
                    'contact_id' => $contactId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($matchingLeads->count() > 0 || $matchingContacts->count() > 0) {
            $description = "Auto-linked thread <b>\"{$emailThread->subject}\"</b> to ";
            $links = [];
            if ($matchingLeads->count() > 0) $links[] = "{$matchingLeads->count()} lead(s)";
            if ($matchingContacts->count() > 0) $links[] = "{$matchingContacts->count()} contact(s)";
            $description .= implode(' and ', $links) . ".";

            $this->logActivity('auto_link', 'Thread auto-linked to CRM', $description, [], [
                'leads_count' => $matchingLeads->count(),
                'contacts_count' => $matchingContacts->count(),
                'thread_id' => $emailThread->id
            ]);

            Log::info('Auto-linked email thread to CRM records', [
                'thread_id' => $emailThread->id,
                'subject' => $emailThread->subject,
                'leads_linked' => $matchingLeads->count(),
                'contacts_linked' => $matchingContacts->count(),
            ]);
        }
    }

    /**
     * Extract a specific header from a Gmail message.
     */
    private function extractHeader($message, string $headerName): ?string
    {
        if (!$message) {
            return null;
        }

        $headers = $message->getPayload()?->getHeaders() ?? [];
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($headerName)) {
                return $header->getValue();
            }
        }

        return null;
    }

    /**
     * Extract all participant emails from a list of messages.
     */
    private function extractParticipants(array $messages): array
    {
        $participants = [];

        foreach ($messages as $message) {
            $from = $this->extractHeader($message, 'From');
            $to = $this->extractHeader($message, 'To');
            $cc = $this->extractHeader($message, 'Cc');

            if ($from) {
                $participants[] = $this->parseEmailAddress($from);
            }
            if ($to) {
                foreach (explode(',', $to) as $addr) {
                    $participants[] = $this->parseEmailAddress(trim($addr));
                }
            }
            if ($cc) {
                foreach (explode(',', $cc) as $addr) {
                    $participants[] = $this->parseEmailAddress(trim($addr));
                }
            }
        }

        return array_values(array_unique(array_filter($participants)));
    }

    /**
     * Parse an email address from a "Name <email>" format.
     */
    private function parseEmailAddress(string $raw): string
    {
        if (preg_match('/<(.+?)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return strtolower(trim($raw));
    }

    /**
     * Parse the name from a "Name <email>" format.
     */
    private function parseEmailName(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(.+?)\s*</', $raw, $matches)) {
            $name = trim($matches[1], ' "\'');
            return $name ?: null;
        }

        return null;
    }

    /**
     * Parse the date from a Gmail message.
     */
    private function parseMessageDate($message): \Carbon\Carbon
    {
        $dateHeader = $this->extractHeader($message, 'Date');
        if ($dateHeader) {
            try {
                return \Carbon\Carbon::parse($dateHeader)->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Exception $e) {
                // Fallback to internalDate
            }
        }

        // Gmail internalDate is in milliseconds
        $internalDate = $message->getInternalDate();
        if ($internalDate) {
            return \Carbon\Carbon::createFromTimestampMs($internalDate)->setTimezone(config('app.timezone', 'UTC'));
        }

        return now();
    }

    /**
     * Collect all unique labels from a thread's messages.
     */
    private function extractThreadLabels(array $messages): array
    {
        $labels = [];
        foreach ($messages as $message) {
            $msgLabels = $message->getLabelIds() ?? [];
            $labels = array_merge($labels, $msgLabels);
        }

        return array_values(array_unique($labels));
    }

    /**
     * Upsert a single email message into the database.
     */
    private function upsertMessage(EmailThread $emailThread, $message, int $companyId): void
    {
        $fromRaw = $this->extractHeader($message, 'From') ?? '';
        $toRaw = $this->extractHeader($message, 'To') ?? '';
        $ccRaw = $this->extractHeader($message, 'Cc');

        $toEmails = array_filter(array_map(
            fn($addr) => $this->parseEmailAddress(trim($addr)),
            explode(',', $toRaw)
        ));

        $ccEmails = $ccRaw
            ? array_filter(array_map(
                fn($addr) => $this->parseEmailAddress(trim($addr)),
                explode(',', $ccRaw)
            ))
            : null;

        // Extract body
        $bodyHtml = $this->extractBody($message, 'text/html');
        $bodyText = $this->extractBody($message, 'text/plain');
        $bodyPreview = $message->getSnippet() ?? mb_substr(strip_tags($bodyHtml ?: $bodyText ?: ''), 0, 200);

        // Extract the Message-ID header for proper email threading across clients
        $messageIdHeader = $this->extractHeader($message, 'Message-ID')
            ?? $this->extractHeader($message, 'Message-Id');

        $emailMessage = EmailMessage::updateOrCreate(
            [
                'email_thread_id' => $emailThread->id,
                'gmail_message_id' => $message->getId(),
            ],
            [
                'from_email' => $this->parseEmailAddress($fromRaw),
                'from_name' => $this->parseEmailName($fromRaw),
                'to_emails' => array_values($toEmails),
                'cc_emails' => $ccEmails ? array_values($ccEmails) : null,
                'subject' => $this->extractHeader($message, 'Subject'),
                'body_preview' => $bodyPreview,
                'body_html' => $bodyHtml ?: $bodyText,
                'sent_at' => $this->parseMessageDate($message),
                'gmail_labels' => $message->getLabelIds(),
                'message_id_header' => $messageIdHeader,
                'created_by' => $companyId,
            ]
        );

        // Sync attachments from Gmail
        $this->syncAttachments($emailMessage, $message);
    }

    /**
     * Download and store attachments from a Gmail message using Spatie Media Library.
     */
    private function syncAttachments(EmailMessage $emailMessage, $gmailMessage): void
    {
        // Skip if this message already has attachments synced
        if ($emailMessage->getMedia('attachments')->count() > 0) {
            return;
        }

        $payload = $gmailMessage->getPayload();
        if (!$payload) {
            return;
        }

        $parts = $this->collectAttachmentParts($payload->getParts() ?? []);

        foreach ($parts as $part) {
            try {
                $attachmentId = $part->getBody()?->getAttachmentId();
                $filename = $part->getFilename();

                if (!$attachmentId || !$filename) {
                    continue;
                }

                // Download the attachment data from Gmail
                $attachmentData = $this->gmail->users_messages_attachments->get(
                    'me',
                    $gmailMessage->getId(),
                    $attachmentId
                );

                $rawData = $this->decodeBody($attachmentData->getData());

                // Save to a temp file and add to Spatie Media Library
                $tempPath = tempnam(sys_get_temp_dir(), 'gmail_attach_');
                file_put_contents($tempPath, $rawData);

                $emailMessage->addMedia($tempPath)
                    ->usingFileName($filename)
                    ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                    ->toMediaCollection('attachments');

            } catch (\Exception $e) {
                Log::warning('Failed to sync attachment', [
                    'gmail_message_id' => $gmailMessage->getId(),
                    'filename' => $filename ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Recursively collect all MIME parts that have a filename (attachments).
     */
    private function collectAttachmentParts(array $parts): array
    {
        $attachments = [];

        foreach ($parts as $part) {
            if ($part->getFilename()) {
                $attachments[] = $part;
            }

            $subParts = $part->getParts() ?? [];
            if (!empty($subParts)) {
                $attachments = array_merge($attachments, $this->collectAttachmentParts($subParts));
            }
        }

        return $attachments;
    }

    /**
     * Extract the email body of a specific MIME type.
     */
    private function extractBody($message, string $mimeType): ?string
    {
        $payload = $message->getPayload();
        if (!$payload) {
            return null;
        }

        // Check the main payload body
        if ($payload->getMimeType() === $mimeType && $payload->getBody()?->getData()) {
            return $this->decodeBody($payload->getBody()->getData());
        }

        // Check parts recursively
        return $this->findBodyInParts($payload->getParts() ?? [], $mimeType);
    }

    /**
     * Recursively search message parts for a specific MIME type body.
     */
    private function findBodyInParts(array $parts, string $mimeType): ?string
    {
        foreach ($parts as $part) {
            if ($part->getMimeType() === $mimeType && $part->getBody()?->getData()) {
                return $this->decodeBody($part->getBody()->getData());
            }

            // Check nested parts
            $subParts = $part->getParts() ?? [];
            if (!empty($subParts)) {
                $result = $this->findBodyInParts($subParts, $mimeType);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Decode base64url-encoded body data from Gmail API.
     */
    private function decodeBody(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Resolve the company owner ID for multi-tenancy.
     */
    private function resolveCompanyId(): int
    {
        $user = $this->account->user;
        if ($user && method_exists($user, 'creatorId')) {
            return $user->creatorId();
        }

        return $this->account->user_id;
    }

    /**
     * Log an activity for the current Gmail account.
     */
    private function logActivity(string $type, string $title, ?string $description = null, array $old = [], array $new = [])
    {
        try {
            \App\Models\GmailAccountActivity::create([
                'gmail_account_id' => $this->account->id,
                'user_id' => auth()->id() ?? $this->account->user_id,
                'activity_type' => $type,
                'title' => $title,
                'description' => $description,
                'old_values' => $old,
                'new_values' => $new,
                'created_by' => $this->account->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log Gmail activity', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
