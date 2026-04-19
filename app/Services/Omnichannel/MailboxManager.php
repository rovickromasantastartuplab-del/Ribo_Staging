<?php

namespace App\Services\Omnichannel;

use App\Contracts\Omnichannel\MailboxProvider;
use App\Models\ChannelAccount;
use App\Services\Omnichannel\Drivers\GmailProvider;
use App\Services\Omnichannel\Drivers\SmtpImapProvider;
use InvalidArgumentException;

class MailboxManager
{
    /**
     * Resolve the appropriate MailboxProvider for a given account.
     */
    public static function resolve(ChannelAccount $account): MailboxProvider
    {
        return match ($account->type) {
            'gmail' => app(GmailProvider::class),
            'smtp_imap' => app(SmtpImapProvider::class),
            default => throw new InvalidArgumentException("Unsupported mailbox type: {$account->type}"),
        };
    }
    
    /**
     * Helper to resolve by account ID.
     */
    public static function resolveById(int $accountId): MailboxProvider
    {
        $account = ChannelAccount::findOrFail($accountId);
        return self::resolve($account);
    }
}
