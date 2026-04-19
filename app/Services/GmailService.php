<?php

namespace App\Services;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Contact;
use App\Models\GmailAccountActivity;
use App\Services\AI\OpenLoopTaskService;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GmailService
{
    private GoogleClient $client;
    private Gmail $gmail;
    private $account;

    public function __construct($account)
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
        $accessToken = $account instanceof \App\Models\ChannelAccount 
            ? $account->getConfig('access_token') 
            : $account->access_token;
            
        $tokenExpiresAt = $account instanceof \App\Models\ChannelAccount 
            ? $account->getConfig('token_expires_at') 
            : $account->token_expires_at;

        $this->client->setAccessToken([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenExpiresAt
                ? max(0, now()->diffInSeconds(\Carbon\Carbon::parse($tokenExpiresAt), false))
                : 3600,
            'created' => $account->updated_at?->timestamp ?? time(),
        ]);

        $refreshToken = $account instanceof \App\Models\ChannelAccount 
            ? $account->getConfig('refresh_token') 
            : $account->refresh_token;

        if ($refreshToken) {
            $this->client->setAccessType('offline');
        }

        $this->gmail = new Gmail($this->client);
    }

    /**
     * Refresh the access token if it has expired.
     */
    public function refreshTokenIfNeeded(): bool
    {
        $isExpired = $this->account instanceof \App\Models\ChannelAccount
            ? (\Carbon\Carbon::parse($this->account->getConfig('token_expires_at'))->isPast() ?? true)
            : $this->account->isTokenExpired();

        if (!$isExpired) {
            return true;
        }

        $refreshToken = $this->account instanceof \App\Models\ChannelAccount
            ? $this->account->getConfig('refresh_token')
            : $this->account->refresh_token;

        if (!$refreshToken) {
            Log::error('Gmail token expired and no refresh token available', [
                'gmail_account_id' => $this->account->id,
            ]);
            return false;
        }

        try {
            $refreshToken = $this->account instanceof \App\Models\ChannelAccount
                ? $this->account->getConfig('refresh_token')
                : $this->account->refresh_token;

            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $newToken = $this->client->getAccessToken();

            if (isset($newToken['error'])) {
                Log::error('Gmail token refresh failed', [
                    'account_id' => $this->account->id,
                    'error' => $newToken['error'],
                ]);
                return false;
            }

            if ($this->account instanceof \App\Models\ChannelAccount) {
                $config = $this->account->configuration;
                $config['access_token'] = $newToken['access_token'];
                $config['token_expires_at'] = now()->addSeconds($newToken['expires_in'] ?? 3600)->toIso8601String();
                
                if (!empty($newToken['refresh_token'])) {
                    $config['refresh_token'] = $newToken['refresh_token'];
                }

                $this->account->update(['configuration' => $config]);
            } else {
                $updateData = [
                    'access_token' => $newToken['access_token'],
                    'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ];

                if (!empty($newToken['refresh_token'])) {
                    $updateData['refresh_token'] = $newToken['refresh_token'];
                }

                $this->account->update($updateData);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Gmail token refresh exception', [
                'account_id' => $this->account->id,
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
        ];

        // Build query based on sync strategy
        $baseQuery = 'in:inbox OR in:sent';
        
        $syncStrategy = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->getConfig('sync_strategy') 
            : $this->account->sync_strategy;
            
        $syncCategories = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->getConfig('sync_categories') 
            : $this->account->sync_categories;

        if ($syncStrategy === 'categories' && !empty($syncCategories)) {
            $categoryQueries = array_map(function($category) {
                return 'category:' . strtolower($category);
            }, $syncCategories);
            
            $params['q'] = '(' . implode(' OR ', $categoryQueries) . ') AND (' . $baseQuery . ')';
        } else {
            $params['q'] = $baseQuery;
        }

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
     * Sync threads from Gmail into the CRM database (paged).
     * Now accepts an optional pageToken for infinite scrolling.
     */
    public function syncThreads(int $maxResults = 50, ?string $pageToken = null): array
    {
        $stats = ['synced' => 0, 'errors' => 0];

        try {
            $result = $this->listThreads($maxResults, $pageToken);
            $companyId = $this->resolveCompanyId();

            // Track the latest historyId from any thread for incremental sync baseline
            $latestHistoryId = null;

            foreach ($result['threads'] as $threadMeta) {
                try {
                    // OPTIMIZATION: Check if the thread already exists with the same snippet to avoid expensive full fetch (Fix 2.3)
                    // This avoids over-engineering with new schema columns while significantly reducing API calls.
                    $existingThread = EmailThread::where('gmail_thread_id', $threadMeta->getId())
                        ->select('snippet')
                        ->first();
                        
                    if ($existingThread && $existingThread->snippet === $threadMeta->getSnippet()) {
                        $stats['synced']++;
                        continue;
                    }

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
            if ($this->account instanceof \App\Models\ChannelAccount) {
                $config = $this->account->configuration;
                $config['last_history_id'] = $latestHistoryId ?? ($config['last_history_id'] ?? null);
                $config['next_page_token'] = $result['nextPageToken'];
                
                $this->account->update([
                    'last_sync_at' => now(),
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'configuration' => $config,
                ]);
            } else {
                $this->account->update([
                    'last_sync_at' => now(),
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'last_history_id' => $latestHistoryId ?? $this->account->last_history_id,
                    'next_page_token' => $result['nextPageToken'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Gmail sync failed', [
                'gmail_account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            $this->account->update([
                'sync_status' => 'error',
                'sync_error' => $e->getMessage(),
            ]);

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
        $startHistoryId = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->getConfig('last_history_id') 
            : $this->account->last_history_id;

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
                'historyTypes' => ['messageAdded', 'messageDeleted', 'labelAdded', 'labelRemoved'],
            ]);

            $historyRecords = $response->getHistory() ?? [];
            $latestHistoryId = $response->getHistoryId();

            // Collect unique thread IDs that changed (messages or labels)
            $changedThreadIds = [];
            foreach ($historyRecords as $historyRecord) {
                // Check messages
                $messagesAdded = $historyRecord->getMessagesAdded() ?? [];
                foreach ($messagesAdded as $addedMsg) {
                    $msg = $addedMsg->getMessage();
                    if ($msg && $msg->getThreadId()) {
                        $changedThreadIds[$msg->getThreadId()] = true;
                    }
                }
                
                // Check label changes (e.g. read/unread status)
                $labelsAdded = $historyRecord->getLabelsAdded() ?? [];
                foreach ($labelsAdded as $labelAdded) {
                    $msg = $labelAdded->getMessage();
                    if ($msg && $msg->getThreadId()) {
                        $changedThreadIds[$msg->getThreadId()] = true;
                    }
                }

                $labelsRemoved = $historyRecord->getLabelsRemoved() ?? [];
                foreach ($labelsRemoved as $labelRemoved) {
                    $msg = $labelRemoved->getMessage();
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
            if ($this->account instanceof \App\Models\ChannelAccount) {
                $config = $this->account->configuration;
                $config['last_history_id'] = $latestHistoryId;
                
                $this->account->update([
                    'last_sync_at' => now(),
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'configuration' => $config,
                ]);
            } else {
                $this->account->update([
                    'last_sync_at' => now(),
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'last_history_id' => $latestHistoryId,
                ]);
            }

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
                'gmail_account_id' => $this->account instanceof \App\Models\GmailAccount ? $this->account->id : null,
                'channel_account_id' => $this->account instanceof \App\Models\ChannelAccount ? $this->account->id : null,
                'external_thread_id' => $thread->getId(),
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
                'channel_type' => 'gmail',
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
        $this->syncOpenLoopTasks($emailThread, $companyId);
    }

    /**
     * Get connection status for display.
     */
    public function getConnectionStatus(): array
    {
        $email = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->email_address 
            : $this->account->gmail_address;

        $isExpired = $this->account instanceof \App\Models\ChannelAccount
            ? (\Carbon\Carbon::parse($this->account->getConfig('token_expires_at'))->isPast() ?? true)
            : $this->account->isTokenExpired();

        $needsReconnect = $this->account instanceof \App\Models\ChannelAccount
            ? (empty($this->account->getConfig('refresh_token')) || $this->account->sync_status === 'error')
            : $this->account->needsReconnect();

        return [
            'connected' => true,
            'email' => $email,
            'last_sync_at' => $this->account->last_sync_at?->toIso8601String(),
            'sync_status' => $this->account->sync_status,
            'sync_error' => $this->account->sync_error,
            'token_expired' => $isExpired,
            'needs_reconnect' => $needsReconnect,
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
     * Archive a thread in Gmail (removes INBOX label).
     *
     * @param string $threadId  Gmail thread ID
     */
    public function archiveThread(string $threadId): bool
    {
        try {
            $this->refreshTokenIfNeeded();
            
            $mods = new \Google\Service\Gmail\ModifyThreadRequest();
            $mods->setRemoveLabelIds(['INBOX']);
            
            $this->gmail->users_threads->modify('me', $threadId, $mods);

            $this->logActivity('email_archived', 'Thread archived', "Gmail Thread ID: {$threadId}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to archive Gmail thread', [
                'gmail_account_id' => $this->account->id,
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unarchive a thread in Gmail (restores INBOX label).
     *
     * @param string $threadId  Gmail thread ID
     */
    public function unarchiveThread(string $threadId): bool
    {
        try {
            $this->refreshTokenIfNeeded();
            
            $mods = new \Google\Service\Gmail\ModifyThreadRequest();
            $mods->setAddLabelIds(['INBOX']);
            
            $this->gmail->users_threads->modify('me', $threadId, $mods);

            $this->logActivity('email_unarchived', 'Thread restored to Inbox', "Gmail Thread ID: {$threadId}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to unarchive Gmail thread', [
                'gmail_account_id' => $this->account->id,
                'thread_id' => $threadId,
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
    public function sendMessage(string $to, string $subject, string $body, ?string $threadId = null, ?string $inReplyTo = null, array $cc = [], array $attachments = [], array $bcc = []): bool
    {
        try {
            // Minimal sanitization to prevent CRLF injection
            $to = str_replace(["\r", "\n"], '', $to);
            $subject = str_replace(["\r", "\n"], '', $subject);
            
            $this->refreshTokenIfNeeded();

            // For replies, ensure Subject starts with "Re:" for proper recipient-side threading
            if ($inReplyTo && !preg_match('/^Re:\s/i', $subject)) {
                $subject = 'Re: ' . $subject;
            }

            $message = new \Google\Service\Gmail\Message();

            if (!empty($attachments)) {
                $rawMessage = $this->buildMultipartMessage($to, $subject, $body, $inReplyTo, $cc, $attachments, $bcc);
            } else {
                $rawMessage = $this->buildSimpleMessage($to, $subject, $body, $inReplyTo, $cc, $bcc);
            }

            // Base64Url encode
            $encodedMessage = strtr(base64_encode($rawMessage), '+/', '-_');
            $message->setRaw($encodedMessage);

            if ($threadId) {
                $message->setThreadId($threadId);
            }

            $sentMessage = $this->gmail->users_messages->send('me', $message);

            // Record the sent message locally with staff attribution
            $this->recordSentMessage($sentMessage, $to, $subject, $body, $bcc);

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
    private function buildSimpleMessage(string $to, string $subject, string $body, ?string $inReplyTo, array $cc, array $bcc = []): string
    {
        $fromEmail = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->email_address 
            : $this->account->gmail_address;

        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$to}\r\n";

        if (!empty($cc)) {
            $rawMessage .= "Cc: " . implode(', ', $cc) . "\r\n";
        }

        if (!empty($bcc)) {
            $rawMessage .= "Bcc: " . implode(', ', $bcc) . "\r\n";
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
    private function buildMultipartMessage(string $to, string $subject, string $body, ?string $inReplyTo, array $cc, array $attachments, array $bcc = []): string
    {
        $boundary = 'boundary_' . md5(uniqid(mt_rand(), true));

        $fromEmail = $this->account instanceof \App\Models\ChannelAccount 
            ? $this->account->email_address 
            : $this->account->gmail_address;

        $rawMessage = "From: {$fromEmail}\r\n";
        $rawMessage .= "To: {$to}\r\n";

        if (!empty($cc)) {
            $rawMessage .= "Cc: " . implode(', ', $cc) . "\r\n";
        }

        if (!empty($bcc)) {
            $rawMessage .= "Bcc: " . implode(', ', $bcc) . "\r\n";
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

        // Filter out the connected Gmail address and extract clean emails from strings like "Name <email@example.com>"
        $externalParticipants = array_map(function ($participant) {
            if (preg_match('/<(.+?)>/', $participant, $matches)) {
                return strtolower(trim($matches[1]));
            }
            return strtolower(trim($participant));
        }, $participants);

        $selfEmail = strtolower($this->account instanceof \App\Models\ChannelAccount ? $this->account->email_address : $this->account->gmail_address);
        $externalParticipants = array_unique(array_filter($externalParticipants, function ($email) use ($selfEmail) {
            return !empty($email) && $email !== $selfEmail;
        }));

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
        $bccRaw = $this->extractHeader($message, 'Bcc');

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

        $bccEmails = $bccRaw
            ? array_filter(array_map(
                fn($addr) => $this->parseEmailAddress(trim($addr)),
                explode(',', $bccRaw)
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
                'external_message_id' => $message->getId(),
            ],
            [
                'from_email' => $this->parseEmailAddress($fromRaw),
                'from_name' => $this->parseEmailName($fromRaw),
                'to_emails' => array_values($toEmails),
                'cc_emails' => $ccEmails ? array_values($ccEmails) : null,
                'bcc_emails' => $bccEmails ? array_values($bccEmails) : null,
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
     * Extract and store attachment metadata from a Gmail message to enable lazy-loading.
     */
    private function syncAttachments(EmailMessage $emailMessage, $gmailMessage): void
    {
        $payload = $gmailMessage->getPayload();
        if (!$payload) return;

        $parts = $this->collectAttachmentParts($payload->getParts() ?? []);
        $attachments = [];

        foreach ($parts as $part) {
            $attachmentId = $part->getBody()?->getAttachmentId();
            if ($attachmentId) {
                $attachments[] = [
                    'id' => $attachmentId,
                    'name' => $part->getFilename(),
                    'size' => $part->getBody()?->getSize(),
                    'content_type' => $part->getMimeType(),
                ];
            }
        }

        if (!empty($attachments)) {
            $metadata = $emailMessage->metadata ?? [];
            $metadata['attachments'] = $attachments;
            $emailMessage->update(['metadata' => $metadata]);
        }
    }

    /**
     * Fetch attachment metadata for a message directly from the Gmail API.
     */
    public function getMessageAttachmentsInfo(string $gmailMessageId): array
    {
        try {
            $this->refreshTokenIfNeeded();
            
            // Use 'metadata' format with only parts/filename fields to keep the request lightweight
            $message = $this->gmail->users_messages->get('me', $gmailMessageId, [
                'format' => 'full', 
            ]);

            $payload = $message->getPayload();
            if (!$payload) {
                return [];
            }

            $parts = $this->collectAttachmentParts($payload->getParts() ?? []);
            $attachments = [];

            foreach ($parts as $part) {
                $attachmentId = $part->getBody()?->getAttachmentId();
                if ($attachmentId) {
                    $attachments[] = [
                        'attachment_id' => $attachmentId,
                        'file_name' => $part->getFilename(),
                        'mime_type' => $part->getMimeType(),
                        'size' => $part->getBody()?->getSize(),
                    ];
                }
            }

            return $attachments;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch Gmail attachment info', [
                'gmail_message_id' => $gmailMessageId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Download the raw data for a specific attachment from the Gmail API.
     */
    public function downloadAttachmentRaw(string $gmailMessageId, string $attachmentId): ?array
    {
        try {
            $this->refreshTokenIfNeeded();

            $attachment = $this->gmail->users_messages_attachments->get('me', $gmailMessageId, $attachmentId);
            
            return [
                'data' => $this->decodeBody($attachment->getData()),
                'size' => $attachment->getSize(),
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download raw Gmail attachment', [
                'gmail_message_id' => $gmailMessageId,
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            return null;
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
    /**
     * Record a sent message in the local database with staff member attribution.
     */
    private function recordSentMessage($sentMessage, $to, $subject, $body, array $bcc_emails = []): void
    {
        try {
            $companyId = $this->resolveCompanyId();
            
            // Find or create the local thread
            $emailThread = \App\Models\EmailThread::updateOrCreate(
                [
                    'external_thread_id' => $sentMessage->getThreadId(),
                    'created_by' => $companyId,
                ],
                [
                    'subject' => $subject,
                    'is_read' => true,
                    'last_message_at' => now(),
                    'gmail_account_id' => $this->account instanceof \App\Models\GmailAccount ? $this->account->id : null,
                    'channel_account_id' => $this->account instanceof \App\Models\ChannelAccount ? $this->account->id : null,
                    'channel_type' => 'gmail',
                ]
            );

            // Create the local message record with the authenticated user's ID
            \App\Models\EmailMessage::updateOrCreate(
                [
                    'email_thread_id' => $emailThread->id,
                    'external_message_id' => $sentMessage->getId(),
                ],
                [
                    'from_email' => $this->account instanceof \App\Models\ChannelAccount ? $this->account->email_address : $this->account->gmail_address,
                    'from_name' => $this->account->name,
                    'to_emails' => [$to],
                    'bcc_emails' => $bcc_emails ?: null,
                    'subject' => $subject,
                    'body_preview' => mb_substr(strip_tags($body), 0, 200),
                    'body_html' => $body,
                    'sent_at' => now(),
                    'gmail_labels' => $sentMessage->getLabelIds() ?: ['SENT'],
                    'created_by' => $companyId,
                    'user_id' => auth()->id(),
                ]
            );

            // Auto-link the thread to lead/contact if it was just created or emails match
            $this->autoLinkThread($emailThread, $companyId);
            $this->syncOpenLoopTasks($emailThread, $companyId);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to record sent message locally', [
                'error' => $e->getMessage(),
                'thread_id' => $sentMessage->getThreadId()
            ]);
        }
    }

    /**
     * Log an activity for the current Gmail account.
     */
    private function logActivity(string $type, string $title, ?string $description = null, array $old = [], array $new = [])
    {
        try {
            $column = $this->account instanceof \App\Models\ChannelAccount ? 'channel_account_id' : 'gmail_account_id';
            
            \App\Models\GmailAccountActivity::create([
                $column => $this->account->id,
                'user_id' => auth()->id() ?? $this->account->user_id,
                'activity_type' => $type,
                'title' => $title,
                'description' => $description,
                'old_values' => $old,
                'new_values' => $new,
                'created_by' => $this->resolveCompanyId(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to log Gmail activity', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function syncOpenLoopTasks(EmailThread $thread, int $companyId): void
    {
        try {
            app(OpenLoopTaskService::class)->upsertFromThread($thread, $companyId);
        } catch (\Throwable $e) {
            Log::warning('Open-loop task sync failed for thread', [
                'email_thread_id' => $thread->id,
                'gmail_thread_id' => $thread->gmail_thread_id,
                'created_by' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a Gmail thread has received a reply (more than 1 message).
     * Fails open: returns false on API error so we never cancel a follow-up due to connectivity.
     */
    public function hasReply(string $gmailThreadId, string $recipientEmail): bool
    {
        try {
            $this->refreshTokenIfNeeded();

            // Fetch the entire thread to see all messages
            $thread = $this->gmail->users_threads->get('me', $gmailThreadId, [
                'format' => 'metadata',
                'metadataHeaders' => ['From'],
            ]);

            $messages = $thread->getMessages() ?? [];

            // If only one message exists, no reply is possible (it's the original outreach)
            if (count($messages) <= 1) {
                return false;
            }

            foreach ($messages as $message) {
                $payload = $message->getPayload();
                $headers = $payload ? $payload->getHeaders() : [];
                $fromHeader = '';

                foreach ($headers as $header) {
                    if (strtolower($header->getName()) === 'from') {
                        $fromHeader = $header->getValue();
                        break;
                    }
                }

                if (empty($fromHeader)) {
                    continue;
                }

                // Extract email from "Name <email@example.com>" or just "email@example.com"
                $email = $fromHeader;
                if (preg_match('/<([^>]+)>/', $fromHeader, $matches)) {
                    $email = $matches[1];
                }

                // If this message was sent by the client, it counts as a reply.
                // We ignore messages from our own Gmail account address.
                if (strtolower(trim($email)) === strtolower(trim($recipientEmail))) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('hasReply check failed, failing open (no cancel)', [
                'gmail_thread_id' => $gmailThreadId,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an automated follow-up reply in an existing Gmail thread.
     * Constructs proper In-Reply-To and References headers for correct threading.
     *
     * @return string|null  The new Gmail message ID on success, null on failure.
     */
    public function sendFollowUpReply(\App\Models\ThreadFollowUpQueue $item, string $body): ?string
    {
        try {
            $this->refreshTokenIfNeeded();

            // Fetch the original message to get threading headers
            $originalMessage = $this->gmail->users_messages->get('me', $item->gmail_message_id, [
                'format' => 'metadata',
                'metadataHeaders' => ['Subject', 'Message-ID', 'References'],
            ]);

            $subject = $this->extractHeader($originalMessage, 'Subject') ?? '';
            $messageId = $this->extractHeader($originalMessage, 'Message-ID');
            $references = $this->extractHeader($originalMessage, 'References');

            // Ensure subject starts with "Re:" for proper threading
            if (!preg_match('/^Re:\s/i', $subject)) {
                $subject = 'Re: ' . $subject;
            }

            // Build References chain: existing references + the message we're replying to
            $referencesChain = trim(($references ? $references . ' ' : '') . ($messageId ?? ''));

            // Build the raw RFC 2822 message
            $rawMessage = "From: {$this->account->gmail_address}\r\n";
            $rawMessage .= "To: {$item->recipient_email}\r\n";
            $rawMessage .= "Subject: {$subject}\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
            $rawMessage .= "MIME-Version: 1.0\r\n";

            if ($messageId) {
                $rawMessage .= "In-Reply-To: {$messageId}\r\n";
            }
            if ($referencesChain) {
                $rawMessage .= "References: {$referencesChain}\r\n";
            }

            $rawMessage .= "\r\n{$body}";

            // Encode and send
            $gmailMessage = new \Google\Service\Gmail\Message();
            $gmailMessage->setRaw(strtr(base64_encode($rawMessage), '+/', '-_'));
            $gmailMessage->setThreadId($item->gmail_thread_id);

            $sentMessage = $this->gmail->users_messages->send('me', $gmailMessage);

            // Record locally
            $this->recordSentMessage($sentMessage, $item->recipient_email, $subject, $body);

            Log::info('Follow-up reply sent', [
                'gmail_thread_id' => $item->gmail_thread_id,
                'recipient' => $item->recipient_email,
                'new_message_id' => $sentMessage->getId(),
            ]);

            return $sentMessage->getId();
        } catch (\Exception $e) {
            Log::error('Failed to send follow-up reply', [
                'queue_id' => $item->id,
                'gmail_thread_id' => $item->gmail_thread_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
