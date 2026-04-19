<?php

namespace App\Contracts\Omnichannel;

use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;

interface MailboxProvider
{
    /**
     * Synchronize inbound messages for the given account.
     * Returns an array of synced message IDs or stats.
     */
    public function syncInbound(ChannelAccount $account): array;

    /**
     * Send an outgoing message.
     */
    public function sendOutgoing(EmailMessage $message): bool;

    /**
     * Download attachment metadata or binary from the provider.
     */
    public function downloadAttachment(EmailMessage $message, string $attachmentId): string;

    /**
     * Update the status or labels of a thread on the remote provider.
     */
    public function updateThreadStatus(EmailThread $thread, string $status): bool;
    /**
     * Get live attachment metadata from the provider (if available).
     */
    public function getLiveAttachments(\App\Models\EmailMessage $message): array;
}
