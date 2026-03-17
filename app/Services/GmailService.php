<?php

namespace App\Services;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
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
        $superadmin = \App\Models\User::where('type', 'superadmin')->first();
        $superadminId = $superadmin?->id;

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
            'labelIds' => ['INBOX'],
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
     * Sync threads from Gmail into the CRM database.
     */
    public function syncThreads(int $maxResults = 50): array
    {
        $stats = ['synced' => 0, 'errors' => 0];

        try {
            $result = $this->listThreads($maxResults);
            $companyId = $this->resolveCompanyId();

            foreach ($result['threads'] as $threadMeta) {
                try {
                    $thread = $this->getThread($threadMeta->getId());
                    if (!$thread) {
                        $stats['errors']++;
                        continue;
                    }

                    $messages = $thread->getMessages() ?? [];
                    $firstMessage = $messages[0] ?? null;
                    $lastMessage = end($messages) ?: $firstMessage;

                    // Extract subject from first message headers
                    $subject = $this->extractHeader($firstMessage, 'Subject');
                    $participants = $this->extractParticipants($messages);

                    // Upsert the thread
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
                            'is_read' => true,
                            'labels' => $this->extractThreadLabels($messages),
                            'created_by' => $companyId,
                        ]
                    );

                    // Upsert each message
                    foreach ($messages as $message) {
                        $this->upsertMessage($emailThread, $message, $companyId);
                    }

                    $stats['synced']++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync Gmail thread', [
                        'thread_id' => $threadMeta->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            // Update account sync status
            $this->account->update([
                'last_sync_at' => now(),
                'sync_status' => 'idle',
                'sync_error' => null,
            ]);

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
                return \Carbon\Carbon::parse($dateHeader);
            } catch (\Exception $e) {
                // Fallback to internalDate
            }
        }

        // Gmail internalDate is in milliseconds
        $internalDate = $message->getInternalDate();
        if ($internalDate) {
            return \Carbon\Carbon::createFromTimestampMs($internalDate);
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

        EmailMessage::updateOrCreate(
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
                'created_by' => $companyId,
            ]
        );
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
}
