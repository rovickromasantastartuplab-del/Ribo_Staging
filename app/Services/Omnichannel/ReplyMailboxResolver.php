<?php

namespace App\Services\Omnichannel;

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use Illuminate\Validation\ValidationException;

class ReplyMailboxResolver
{
    public function resolve(EmailThread $thread): ChannelAccount
    {
        $thread->loadMissing('channelAccount');

        $account = $thread->channelAccount;

        if (!$account) {
            throw ValidationException::withMessages([
                'mailbox' => 'This conversation is not linked to a mailbox. Reconnect or backfill mailbox ownership before replying.',
            ]);
        }

        if ($account->sync_status !== 'active') {
            throw ValidationException::withMessages([
                'mailbox' => 'The mailbox for this conversation is not active. Please reconnect or sync it before replying.',
            ]);
        }

        return $account;
    }
}
