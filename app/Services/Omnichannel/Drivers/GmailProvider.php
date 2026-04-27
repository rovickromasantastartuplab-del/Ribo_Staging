<?php

namespace App\Services\Omnichannel\Drivers;

use App\Contracts\Omnichannel\MailboxProvider;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\GmailService;

class GmailProvider implements MailboxProvider
{
    /**
     * Synchronize inbound messages.
     */
    public function syncInbound(ChannelAccount $account): array
    {
        $service = new GmailService($account);

        // Use incremental sync if possible, otherwise full sync
        $stats = $service->incrementalSync();

        // If incremental sync returns null (no history baseline) OR 
        // if it returned zero changes but we have NO threads locally for this account,
        // then force a full sync to ensure the initial population happens.
        $localThreadCount = $account->threads()->count();

        if ($stats === null || ($stats['synced'] === 0 && $localThreadCount === 0)) {
            $stats = $service->syncThreads(50);
        }

        return $stats;
    }

    /**
     * Send an outgoing message.
     */
    public function sendOutgoing(EmailMessage $message): bool
    {
        $thread = $message->thread;
        $account = $thread->channelAccount;
        $service = new GmailService($account);

        return $service->sendMessage(
            to: $message->to_emails[0] ?? '',
            subject: $message->subject,
            body: $message->body_html,
            threadId: $thread->external_thread_id,
            inReplyTo: $message->message_id_header,
            cc: $message->cc_emails ?? [],
            bcc: $message->bcc_emails ?? []
        );
    }

    /**
     * Download attachment.
     */
    public function downloadAttachment(EmailMessage $message, string $attachmentId): string
    {
        $account = $message->thread->channelAccount;
        $service = new GmailService($account);

        $result = $service->downloadAttachmentRaw($message->external_message_id, $attachmentId);

        if ($result && !empty($result['data'])) {
            $tempPath = storage_path('app/temp_attachments/' . uniqid() . '_gmail_attach');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $result['data']);
            return $tempPath;
        }

        return '';
    }

    /**
     * Update thread status (Archive/Unarchive).
     */
    public function updateThreadStatus(EmailThread $thread, string $status): bool
    {
        $service = new GmailService($thread->channelAccount);

        if ($status === 'archived') {
            return $service->archiveThread($thread->external_thread_id);
        }

        if ($status === 'inbox') {
            return $service->unarchiveThread($thread->external_thread_id);
        }

        return false;
    }

    /**
     * Get live attachment metadata from the provider.
     */
    public function getLiveAttachments(\App\Models\EmailMessage $message): array
    {
        return $message->metadata['attachments'] ?? [];
    }
}
