# Multi-Inbox Reply System Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make replies work correctly and predictably when Gmail and private IMAP/SMTP mailboxes are connected at the same time.

**Architecture:** Replies must be mailbox-bound, not default-account-bound. Every reply uses `EmailThread.channel_account_id -> ChannelAccount -> MailboxManager -> provider`, and the frontend displays the exact mailbox used for the reply. Existing threads without `channel_account_id` are repaired through a deterministic backfill before reply is allowed.

**Tech Stack:** Laravel, Inertia React, TypeScript, Pest/PHPUnit, Symfony Mailer, Gmail provider, IMAP/SMTP provider.

---

## File Structure

- Modify `app/Http/Controllers/ConversationController.php`: harden reply account resolution, add user-facing reply mailbox metadata, fix provider-neutral error messages, and reject orphaned threads cleanly.
- Create `app/Services/Omnichannel/ReplyMailboxResolver.php`: single-purpose resolver for “which mailbox should this reply use?”
- Modify `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`: improve SMTP reply headers and support outgoing reply attachments.
- Modify `app/Services/Omnichannel/Drivers/GmailProvider.php`: verify Gmail reply behavior still accepts thread-bound messages.
- Create `app/Console/Commands/BackfillThreadChannelAccounts.php`: repair old threads missing `channel_account_id`.
- Modify `routes/console.php`: register/schedule no command automatically; command is manual.
- Modify `resources/js/pages/conversations/index.tsx`: show “Replying from mailbox@example.com” and block reply when the thread has no mailbox.
- Add `tests/Feature/ConversationReplyMailboxTest.php`: end-to-end reply behavior for Gmail, private IMAP/SMTP, orphaned threads, and selected reply target.
- Add `tests/Unit/Omnichannel/ReplyMailboxResolverTest.php`: resolver behavior tests.

---

## Reply Contract

The final system must obey these rules:

1. A thread with `channel_account_id = Gmail account ID` replies through `GmailProvider`.
2. A thread with `channel_account_id = private mailbox ID` replies through `SmtpImapProvider`.
3. New compose may choose a mailbox, but reply never silently chooses the first mailbox.
4. A reply to an orphaned thread returns a clear `422` response: `This conversation is not linked to a mailbox. Reconnect or backfill mailbox ownership before replying.`
5. The frontend shows the reply sender mailbox before the user sends.
6. Gmail/private provider-specific failure text is not leaked into the wrong provider path.
7. Attachments included in private SMTP replies are sent.

---

### Task 1: Add a Dedicated Reply Mailbox Resolver

**Files:**
- Create: `app/Services/Omnichannel/ReplyMailboxResolver.php`
- Test: `tests/Unit/Omnichannel/ReplyMailboxResolverTest.php`

- [ ] **Step 1: Write failing resolver tests**

Create `tests/Unit/Omnichannel/ReplyMailboxResolverTest.php`:

```php
<?php

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\ReplyMailboxResolver;
use Illuminate\Validation\ValidationException;

it('resolves the channel account attached to the thread', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => 'smtp_imap',
        'external_thread_id' => 'thread-1',
        'subject' => 'Need help',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    $resolved = app(ReplyMailboxResolver::class)->resolve($thread);

    expect($resolved->id)->toBe($account->id);
    expect($resolved->email_address)->toBe('support@example.test');
});

it('rejects orphaned threads instead of choosing a random mailbox', function () {
    $company = User::factory()->create(['type' => 'company']);

    ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'owner@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-thread',
        'subject' => 'Legacy',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    app(ReplyMailboxResolver::class)->resolve($thread);
})->throws(ValidationException::class, 'This conversation is not linked to a mailbox.');

it('rejects inactive mailbox accounts for replies', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'error',
        'sync_error' => 'IMAP login failed',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => 'smtp_imap',
        'external_thread_id' => 'thread-2',
        'subject' => 'Need help',
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    app(ReplyMailboxResolver::class)->resolve($thread);
})->throws(ValidationException::class, 'The mailbox for this conversation is not active.');
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Unit/Omnichannel/ReplyMailboxResolverTest.php
```

Expected: FAIL because `ReplyMailboxResolver` does not exist.

- [ ] **Step 3: Create resolver**

Create `app/Services/Omnichannel/ReplyMailboxResolver.php`:

```php
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
```

- [ ] **Step 4: Run resolver tests**

Run:

```bash
php artisan test tests/Unit/Omnichannel/ReplyMailboxResolverTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Omnichannel/ReplyMailboxResolver.php tests/Unit/Omnichannel/ReplyMailboxResolverTest.php
git commit -m "feat: resolve reply mailbox from thread ownership"
```

---

### Task 2: Harden Backend Reply Flow

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Test: `tests/Feature/ConversationReplyMailboxTest.php`

- [ ] **Step 1: Write failing tests for Gmail/private/orphan reply behavior**

Create `tests/Feature/ConversationReplyMailboxTest.php`:

```php
<?php

use App\Contracts\Omnichannel\MailboxProvider;
use App\Http\Controllers\ConversationController;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\Drivers\GmailProvider;
use App\Services\Omnichannel\Drivers\SmtpImapProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

function makeReplyThread(User $company, ChannelAccount $account): EmailThread
{
    $thread = EmailThread::query()->create([
        'channel_account_id' => $account->id,
        'channel_type' => $account->type,
        'external_thread_id' => 'provider-thread-' . $account->id,
        'subject' => 'Customer question',
        'participants' => [$account->email_address, 'customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
        'last_message_at' => now(),
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'external_message_id' => 'incoming-' . $account->id,
        'message_id_header' => '<incoming-' . $account->id . '@example.test>',
        'from_email' => 'customer@example.test',
        'to_emails' => [$account->email_address],
        'subject' => 'Customer question',
        'body_html' => '<p>Question</p>',
        'created_by' => $company->id,
        'created_at' => now(),
    ]);

    return $thread->fresh('channelAccount');
}

it('replies to gmail threads through the gmail provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $gmail = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'gmail@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $gmail);

    $captured = null;

    app()->instance(GmailProvider::class, new class($captured) extends GmailProvider {
        public function __construct(private mixed &$captured) {}
        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh('thread.channelAccount');
            return true;
        }
    });

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Gmail reply</p>',
    ]);

    $response = app(ConversationController::class)->reply($request, $thread);

    expect($response->getStatusCode())->toBe(200);
    expect($captured->thread->channelAccount->type)->toBe('gmail');
    expect($captured->from_email)->toBe('gmail@example.test');
});

it('replies to private mailbox threads through smtp imap provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'support@example.test',
            'smtp_password' => 'secret',
        ],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $captured = null;

    app()->instance(SmtpImapProvider::class, new class($captured) implements MailboxProvider {
        public function __construct(private mixed &$captured) {}
        public function syncInbound(ChannelAccount $account): array { return ['status' => 'success', 'synced' => 0, 'errors' => 0]; }
        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh('thread.channelAccount');
            return true;
        }
        public function downloadAttachment(EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(EmailMessage $message): array { return []; }
    });

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Private reply</p>',
    ]);

    $response = app(ConversationController::class)->reply($request, $thread);

    expect($response->getStatusCode())->toBe(200);
    expect($captured->thread->channelAccount->type)->toBe('smtp_imap');
    expect($captured->from_email)->toBe('support@example.test');
});

it('rejects replies on orphaned threads with provider neutral message', function () {
    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-thread',
        'subject' => 'Legacy',
        'participants' => ['customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Reply</p>',
    ]);

    $response = app(ConversationController::class)->reply($request, $thread);

    expect($response->getStatusCode())->toBe(422);
    expect(json_decode($response->getContent(), true)['error'])->toContain('not linked to a mailbox');
});
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php
```

Expected: orphaned-thread test may fail with the old message, and resolver injection is not used yet.

- [ ] **Step 3: Update reply method to use resolver**

In `app/Http/Controllers/ConversationController.php`, add import:

```php
use App\Services\Omnichannel\ReplyMailboxResolver;
use Illuminate\Validation\ValidationException;
```

Change reply method signature from:

```php
public function reply(EmailThread $thread, Request $request)
```

to:

```php
public function reply(EmailThread $thread, Request $request, ReplyMailboxResolver $replyMailboxResolver)
```

Replace:

```php
$account = $thread->channelAccount;
if (!$account) {
    return response()->json(['error' => 'Mailbox account not found for this thread.'], 422);
}
```

with:

```php
try {
    $account = $replyMailboxResolver->resolve($thread);
} catch (ValidationException $e) {
    return response()->json([
        'error' => collect($e->errors())->flatten()->first(),
    ], 422);
}
```

Replace the provider-specific failure message:

```php
return response()->json(['error' => 'Failed to send reply via Gmail API.'], 500);
```

with:

```php
return response()->json(['error' => 'Failed to send reply via connected mailbox provider.'], 500);
```

- [ ] **Step 4: Run reply tests**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ConversationController.php tests/Feature/ConversationReplyMailboxTest.php
git commit -m "fix: bind replies to thread mailbox"
```

---

### Task 3: Preserve Correct Reply Threading Headers

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`
- Test: `tests/Feature/ConversationReplyMailboxTest.php`

- [ ] **Step 1: Add failing test for reply headers**

Append to `tests/Feature/ConversationReplyMailboxTest.php`:

```php
it('stores reply target header separately for provider threading', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);
    $incoming = $thread->messages()->first();

    $captured = null;

    app()->instance(SmtpImapProvider::class, new class($captured) implements MailboxProvider {
        public function __construct(private mixed &$captured) {}
        public function syncInbound(ChannelAccount $account): array { return ['status' => 'success', 'synced' => 0, 'errors' => 0]; }
        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh();
            return true;
        }
        public function downloadAttachment(EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(EmailMessage $message): array { return []; }
    });

    $this->actingAs($company);

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>Threaded reply</p>',
        'reply_to_message_id' => $incoming->id,
    ]);

    app(ConversationController::class)->reply($request, $thread);

    expect($captured->metadata['reply_to_message_id_header'])->toBe($incoming->message_id_header);
    expect($captured->message_id_header)->not->toBe($incoming->message_id_header);
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php --filter="stores reply target header"
```

Expected: FAIL because current code stores the target header in `message_id_header`.

- [ ] **Step 3: Update reply message creation**

In `ConversationController@reply`, replace:

```php
'message_id_header' => $replyToHeader, // Bridging for driver
```

with:

```php
'message_id_header' => sprintf('<crm-%s-%s@%s>', $thread->id, (string) \Illuminate\Support\Str::uuid(), parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
'metadata' => [
    'reply_to_message_id_header' => $replyToHeader,
],
```

Add the import:

```php
use Illuminate\Support\Str;
```

Then simplify the expression if using imported `Str`:

```php
'message_id_header' => sprintf('<crm-%s-%s@%s>', $thread->id, (string) Str::uuid(), parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
```

- [ ] **Step 4: Update SMTP provider headers**

In `SmtpImapProvider::sendOutgoing()`, replace:

```php
if ($message->message_id_header) {
    $email->getHeaders()->addTextHeader('In-Reply-To', $message->message_id_header);
    $email->getHeaders()->addTextHeader('References', $message->message_id_header);
}
```

with:

```php
$replyToHeader = $message->metadata['reply_to_message_id_header'] ?? null;

if ($replyToHeader) {
    $email->getHeaders()->addTextHeader('In-Reply-To', $replyToHeader);
    $email->getHeaders()->addTextHeader('References', $replyToHeader);
}

if ($message->message_id_header) {
    $email->getHeaders()->addIdHeader('Message-ID', trim($message->message_id_header, '<>'));
}
```

- [ ] **Step 5: Run focused reply tests**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ConversationController.php app/Services/Omnichannel/Drivers/SmtpImapProvider.php tests/Feature/ConversationReplyMailboxTest.php
git commit -m "fix: preserve provider reply threading headers"
```

---

### Task 4: Support Attachments in Private SMTP Replies

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`
- Test: `tests/Feature/ConversationReplyMailboxTest.php`

- [ ] **Step 1: Add failing attachment test**

Append to `tests/Feature/ConversationReplyMailboxTest.php`:

```php
it('stores uploaded reply attachments for private smtp provider', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $captured = null;

    app()->instance(SmtpImapProvider::class, new class($captured) implements MailboxProvider {
        public function __construct(private mixed &$captured) {}
        public function syncInbound(ChannelAccount $account): array { return ['status' => 'success', 'synced' => 0, 'errors' => 0]; }
        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->captured = $message->fresh();
            return true;
        }
        public function downloadAttachment(EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(EmailMessage $message): array { return []; }
    });

    $this->actingAs($company);

    $file = \Illuminate\Http\UploadedFile::fake()->create('quote.pdf', 12, 'application/pdf');

    $request = Request::create('/api/conversations/threads/' . $thread->id . '/reply', 'POST', [
        'body' => '<p>See attached</p>',
    ], [], [
        'attachments' => [$file],
    ]);

    app(ConversationController::class)->reply($request, $thread);

    expect($captured->metadata['outgoing_attachments'][0]['name'])->toBe('quote.pdf');
    expect($captured->metadata['outgoing_attachments'][0]['mime'])->toBe('application/pdf');
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php --filter="stores uploaded reply attachments"
```

Expected: FAIL because reply attachments are not stored into message metadata.

- [ ] **Step 3: Store reply attachments before send**

In `ConversationController@reply`, after `EmailMessage::create([...])` and before `$success = $driver->sendOutgoing($message);`, add:

```php
if (!empty($attachments)) {
    $storedAttachments = [];

    foreach ($attachments as $attachment) {
        $path = $attachment->store("outbox_attachments/{$message->id}");

        $storedAttachments[] = [
            'path' => $path,
            'name' => $attachment->getClientOriginalName(),
            'mime' => $attachment->getClientMimeType(),
            'size' => $attachment->getSize(),
        ];
    }

    $message->update([
        'metadata' => array_merge($message->metadata ?? [], [
            'outgoing_attachments' => $storedAttachments,
        ]),
    ]);

    $message = $message->fresh('thread.channelAccount');
}
```

- [ ] **Step 4: Attach files in SMTP provider**

In `SmtpImapProvider::sendOutgoing()`, after CC/BCC handling and before headers, add:

```php
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
```

- [ ] **Step 5: Run reply tests**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ConversationController.php app/Services/Omnichannel/Drivers/SmtpImapProvider.php tests/Feature/ConversationReplyMailboxTest.php
git commit -m "feat: support private smtp reply attachments"
```

---

### Task 5: Expose Reply Mailbox Metadata to Frontend

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `resources/js/pages/conversations/index.tsx`
- Test: `tests/Feature/ConversationReplyMailboxTest.php`

- [ ] **Step 1: Add failing API metadata test**

Append to `tests/Feature/ConversationReplyMailboxTest.php`:

```php
it('returns reply mailbox metadata when showing a thread', function () {
    $company = User::factory()->create(['type' => 'company', 'email_verified_at' => now()]);
    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);
    $thread = makeReplyThread($company, $private);

    $this->actingAs($company)
        ->getJson(route('api.conversations.show', $thread))
        ->assertOk()
        ->assertJsonPath('reply_mailbox.email', 'support@example.test')
        ->assertJsonPath('reply_mailbox.type', 'smtp_imap')
        ->assertJsonPath('reply_mailbox.can_reply', true);
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php --filter="returns reply mailbox metadata"
```

Expected: FAIL because `reply_mailbox` is not returned.

- [ ] **Step 3: Add reply mailbox payload in `show()`**

In `ConversationController@show`, before the response, add:

```php
$replyMailbox = $thread->channelAccount ? [
    'id' => $thread->channelAccount->id,
    'email' => $thread->channelAccount->email_address,
    'type' => $thread->channelAccount->type,
    'sync_status' => $thread->channelAccount->sync_status,
    'can_reply' => $thread->channelAccount->sync_status === 'active',
] : [
    'id' => null,
    'email' => null,
    'type' => null,
    'sync_status' => null,
    'can_reply' => false,
];
```

Add it to the JSON response:

```php
'reply_mailbox' => $replyMailbox,
```

- [ ] **Step 4: Update frontend selected thread state**

In `resources/js/pages/conversations/index.tsx`, wherever `setSelectedThread(response.data.thread)` is called after loading a thread, replace it with:

```tsx
setSelectedThread({
    ...response.data.thread,
    reply_mailbox: response.data.reply_mailbox,
});
```

- [ ] **Step 5: Show reply mailbox above reply editor**

Above the reply editor area in `resources/js/pages/conversations/index.tsx`, add:

```tsx
{selectedThread?.reply_mailbox && (
    <div className="mb-2 flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2 text-xs">
        <span className="text-muted-foreground">
            {selectedThread.reply_mailbox.can_reply
                ? t('Replying from')
                : t('Reply unavailable')}
        </span>
        <span className="font-medium">
            {selectedThread.reply_mailbox.email || t('No mailbox linked')}
        </span>
    </div>
)}
```

- [ ] **Step 6: Block send button when no reply mailbox is available**

Find the reply send button disabled condition and include:

```tsx
|| selectedThread?.reply_mailbox?.can_reply === false
```

If there is no single disabled expression, set the button disabled prop to:

```tsx
disabled={
    submittingReply ||
    selectedThread.status === 'Archive' ||
    selectedThread?.reply_mailbox?.can_reply === false
}
```

- [ ] **Step 7: Run tests and build**

Run:

```bash
php artisan test tests/Feature/ConversationReplyMailboxTest.php
npm run build
```

Expected: tests pass and build exits 0.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ConversationController.php resources/js/pages/conversations/index.tsx tests/Feature/ConversationReplyMailboxTest.php
git commit -m "feat: show reply mailbox in conversations"
```

---

### Task 6: Backfill Legacy Threads Missing Mailbox Ownership

**Files:**
- Create: `app/Console/Commands/BackfillThreadChannelAccounts.php`
- Test: `tests/Feature/BackfillThreadChannelAccountsTest.php`

- [ ] **Step 1: Write failing command test**

Create `tests/Feature/BackfillThreadChannelAccountsTest.php`:

```php
<?php

use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;

it('backfills orphaned threads by matching company mailbox email participants', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'support@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $thread = EmailThread::query()->create([
        'channel_account_id' => null,
        'channel_type' => null,
        'external_thread_id' => 'legacy-1',
        'subject' => 'Legacy support',
        'participants' => ['support@example.test', 'customer@example.test'],
        'status' => 'Open',
        'created_by' => $company->id,
    ]);

    EmailMessage::query()->create([
        'email_thread_id' => $thread->id,
        'from_email' => 'customer@example.test',
        'to_emails' => ['support@example.test'],
        'subject' => 'Legacy support',
        'body_html' => '<p>Hello</p>',
        'created_by' => $company->id,
    ]);

    $this->artisan('conversations:backfill-thread-mailboxes')
        ->expectsOutput('Backfilled 1 thread mailbox link(s).')
        ->assertExitCode(0);

    expect($thread->fresh()->channel_account_id)->toBe($account->id);
    expect($thread->fresh()->channel_type)->toBe('smtp_imap');
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/BackfillThreadChannelAccountsTest.php
```

Expected: FAIL because command does not exist.

- [ ] **Step 3: Create command**

Create `app/Console/Commands/BackfillThreadChannelAccounts.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use Illuminate\Console\Command;

class BackfillThreadChannelAccounts extends Command
{
    protected $signature = 'conversations:backfill-thread-mailboxes {--dry-run : Show matches without saving}';

    protected $description = 'Backfill missing email thread channel_account_id values from mailbox participants.';

    public function handle(): int
    {
        $backfilled = 0;
        $dryRun = (bool) $this->option('dry-run');

        EmailThread::query()
            ->whereNull('channel_account_id')
            ->with('messages')
            ->orderBy('id')
            ->chunkById(100, function ($threads) use (&$backfilled, $dryRun) {
                foreach ($threads as $thread) {
                    $account = $this->matchAccount($thread);

                    if (!$account) {
                        continue;
                    }

                    $backfilled++;

                    if (!$dryRun) {
                        $thread->update([
                            'channel_account_id' => $account->id,
                            'channel_type' => $account->type,
                        ]);
                    }
                }
            });

        $suffix = $dryRun ? ' candidate(s).' : ' thread mailbox link(s).';
        $this->info("Backfilled {$backfilled}{$suffix}");

        return self::SUCCESS;
    }

    private function matchAccount(EmailThread $thread): ?ChannelAccount
    {
        $emails = collect($thread->participants ?? [])
            ->merge($thread->messages->pluck('from_email'))
            ->merge($thread->messages->flatMap(fn ($message) => $message->to_emails ?? []))
            ->filter()
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return null;
        }

        return ChannelAccount::query()
            ->where('user_id', $thread->created_by)
            ->whereIn('email_address', $emails)
            ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")
            ->first();
    }
}
```

- [ ] **Step 4: Ensure command auto-discovery or register manually**

If this Laravel version does not auto-discover commands in `app/Console/Commands`, register it in `app/Console/Kernel.php` if that file exists:

```php
protected $commands = [
    \App\Console\Commands\BackfillThreadChannelAccounts::class,
];
```

If there is no `app/Console/Kernel.php`, Laravel command discovery should pick it up.

- [ ] **Step 5: Run command test**

Run:

```bash
php artisan test tests/Feature/BackfillThreadChannelAccountsTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillThreadChannelAccounts.php tests/Feature/BackfillThreadChannelAccountsTest.php
git commit -m "feat: backfill legacy conversation mailbox ownership"
```

---

### Task 7: Final Verification Matrix

**Files:**
- No code changes unless a verification item fails.

- [ ] **Step 1: Run focused backend tests**

Run:

```bash
php artisan test tests/Unit/Omnichannel/ReplyMailboxResolverTest.php tests/Feature/ConversationReplyMailboxTest.php tests/Feature/BackfillThreadChannelAccountsTest.php
```

Expected: all tests PASS.

- [ ] **Step 2: Run existing compose regression test**

Run:

```bash
php artisan test tests/Feature/ConversationComposeTest.php
```

Expected: PASS.

- [ ] **Step 3: Run syntax checks**

Run:

```bash
php -l app/Services/Omnichannel/ReplyMailboxResolver.php
php -l app/Http/Controllers/ConversationController.php
php -l app/Services/Omnichannel/Drivers/SmtpImapProvider.php
php -l app/Console/Commands/BackfillThreadChannelAccounts.php
```

Expected: each file reports `No syntax errors detected`.

- [ ] **Step 4: Run frontend build**

Run:

```bash
npm run build
```

Expected: Vite build exits 0.

- [ ] **Step 5: Manual Gmail reply test**

Manual steps:

1. Connect Gmail.
2. Sync inbox.
3. Open a Gmail-owned thread.
4. Confirm reply box says `Replying from <gmail address>`.
5. Send a reply.
6. Confirm the recipient receives the message from Gmail.

Expected: reply sends through Gmail provider and stays in the same Gmail thread.

- [ ] **Step 6: Manual private mailbox reply test**

Manual steps:

1. Connect private IMAP/SMTP mailbox.
2. Sync inbox.
3. Open a private-mailbox-owned thread.
4. Confirm reply box says `Replying from <private email>`.
5. Send a reply with one attachment.
6. Confirm the recipient receives the message from the private email address with attachment.

Expected: reply sends through SMTP provider, attachment arrives, and headers preserve email threading.

- [ ] **Step 7: Manual orphaned thread test**

Manual steps:

1. Find or create an old thread with `channel_account_id = null`.
2. Open it in Conversations.
3. Confirm reply box says no mailbox is linked or send is disabled.
4. Run `php artisan conversations:backfill-thread-mailboxes`.
5. Reload thread.
6. Confirm reply mailbox appears if a matching connected mailbox exists.

Expected: no silent fallback to first mailbox.

---

## Final Behavior After This Plan

When Gmail and private IMAP/SMTP both exist:

```text
Gmail thread
-> thread.channel_account_id points to Gmail ChannelAccount
-> reply uses GmailProvider
-> UI says Replying from gmail@example.com

Private mailbox thread
-> thread.channel_account_id points to smtp_imap ChannelAccount
-> reply uses SmtpImapProvider
-> UI says Replying from support@example.com

Legacy/orphan thread
-> no channel_account_id
-> reply is blocked
-> backfill command can repair it when mailbox participants match
```

## Self-Review

- Spec coverage: This plan covers Gmail replies, private SMTP replies, orphaned legacy threads, provider-neutral errors, reply threading headers, reply attachments, frontend mailbox visibility, and manual verification.
- Placeholder scan: No `TBD`, `TODO`, or vague “handle edge cases” instructions remain.
- Type consistency: The plan consistently uses `ReplyMailboxResolver`, `channel_account_id`, `reply_mailbox`, `outgoing_attachments`, and `reply_to_message_id_header`.
