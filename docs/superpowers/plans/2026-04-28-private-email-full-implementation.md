# Private Email Full Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Private Email (IMAP/SMTP) safe to connect, reliable to sync, selectable for sending, and clear to use from the frontend.

**Architecture:** Keep the current omnichannel model: `ChannelAccount` stores provider configuration, `MailboxManager` resolves the provider, and conversations send through the thread/account provider. Add a small verification service so connection testing happens before saving, fix sync status handling so failed IMAP does not become active, and add mailbox selection for new compose while replies continue using their thread mailbox.

**Tech Stack:** Laravel, Inertia React, TypeScript, Webklex IMAP, Symfony Mailer, Pest/PHPUnit, Vite.

---

## File Structure

- Modify `app/Http/Controllers/Settings/ChannelAccountController.php`: validate/store private mailbox accounts, call credential verifier before save, return precise errors.
- Create `app/Services/Omnichannel/SmtpImapCredentialVerifier.php`: verify IMAP login and SMTP connection credentials before saving.
- Modify `app/Jobs/SyncChannelAccountJob.php`: respect provider error status and store the real sync error.
- Modify `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`: return consistent sync results, scope IMAP duplicate detection by account/thread, send attachments through Symfony Mailer, and set better SMTP headers.
- Modify `app/Http/Controllers/ConversationController.php`: pass all active channel accounts to the frontend, choose a requested mailbox for compose, fix the inbox sync endpoint to use `ChannelAccount`, and keep replies bound to the thread account.
- Modify `resources/js/pages/conversations/index.tsx`: add a “From” mailbox selector to compose and send `channel_account_id`.
- Modify `resources/js/pages/settings/components/mailbox-settings.tsx`: align labels with backend behavior and show status/error clearly.
- Add `tests/Feature/Settings/PrivateMailboxConnectionTest.php`: controller-level tests for validation, verification success, and verification failure.
- Add `tests/Feature/ConversationPrivateMailboxTest.php`: compose/sync tests for private mailbox behavior.
- Add `tests/Unit/Omnichannel/SyncChannelAccountJobTest.php`: verifies failed provider sync remains error.

---

### Task 1: Add Private Mailbox Credential Verification

**Files:**
- Create: `app/Services/Omnichannel/SmtpImapCredentialVerifier.php`
- Modify: `app/Http/Controllers/Settings/ChannelAccountController.php`
- Test: `tests/Feature/Settings/PrivateMailboxConnectionTest.php`

- [ ] **Step 1: Write failing tests for verification success and failure**

Create `tests/Feature/Settings/PrivateMailboxConnectionTest.php`:

```php
<?php

use App\Models\ChannelAccount;
use App\Models\User;
use App\Services\Omnichannel\SmtpImapCredentialVerifier;
use Illuminate\Support\Facades\Queue;

it('stores a private mailbox only after credentials are verified', function () {
    Queue::fake();

    $company = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);

    app()->instance(SmtpImapCredentialVerifier::class, new class {
        public function verify(array $config): void
        {
            expect($config['email_address'])->toBe('sales@example.test');
            expect($config['smtp_username'])->toBe('sales@example.test');
        }
    });

    $this->actingAs($company)
        ->post(route('settings.channels.store'), [
            'email_address' => 'sales@example.test',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'sales@example.test',
            'imap_password' => 'secret',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sales@example.test',
            'smtp_password' => 'secret',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ChannelAccount::query()->where('type', 'smtp_imap')->count())->toBe(1);
});

it('does not store a private mailbox when credential verification fails', function () {
    Queue::fake();

    $company = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);

    app()->instance(SmtpImapCredentialVerifier::class, new class {
        public function verify(array $config): void
        {
            throw new RuntimeException('IMAP login failed: invalid credentials');
        }
    });

    $this->actingAs($company)
        ->from(route('settings'))
        ->post(route('settings.channels.store'), [
            'email_address' => 'sales@example.test',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'sales@example.test',
            'imap_password' => 'wrong',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sales@example.test',
            'smtp_password' => 'wrong',
        ])
        ->assertRedirect(route('settings'))
        ->assertSessionHasErrors(['error']);

    expect(ChannelAccount::query()->where('type', 'smtp_imap')->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
php artisan test tests/Feature/Settings/PrivateMailboxConnectionTest.php
```

Expected: FAIL because `SmtpImapCredentialVerifier` does not exist.

- [ ] **Step 3: Create the credential verifier**

Create `app/Services/Omnichannel/SmtpImapCredentialVerifier.php`:

```php
<?php

namespace App\Services\Omnichannel;

use RuntimeException;
use Symfony\Component\Mailer\Transport;
use Webklex\IMAP\Facades\Client;

class SmtpImapCredentialVerifier
{
    public function verify(array $config): void
    {
        $this->verifyImap($config);
        $this->verifySmtp($config);
    }

    private function verifyImap(array $config): void
    {
        try {
            $client = Client::make([
                'host' => $config['imap_host'],
                'port' => (int) $config['imap_port'],
                'encryption' => $config['imap_encryption'] === 'none' ? false : $config['imap_encryption'],
                'validate_cert' => true,
                'username' => $config['imap_username'],
                'password' => $config['imap_password'],
                'protocol' => 'imap',
            ]);

            $client->connect();
            $client->disconnect();
        } catch (\Throwable $e) {
            throw new RuntimeException('IMAP verification failed: ' . $e->getMessage(), previous: $e);
        }
    }

    private function verifySmtp(array $config): void
    {
        try {
            $transport = Transport::fromDsn($this->smtpDsn($config));
            $transport->start();
            $transport->stop();
        } catch (\Throwable $e) {
            throw new RuntimeException('SMTP verification failed: ' . $e->getMessage(), previous: $e);
        }
    }

    private function smtpDsn(array $config): string
    {
        $scheme = $config['smtp_encryption'] === 'ssl' ? 'smtps' : 'smtp';
        $user = rawurlencode($config['smtp_username']);
        $pass = rawurlencode($config['smtp_password']);
        $host = $config['smtp_host'];
        $port = (int) $config['smtp_port'];

        $query = $config['smtp_encryption'] === 'tls' ? '?auto_tls=true' : '?auto_tls=false';

        return "{$scheme}://{$user}:{$pass}@{$host}:{$port}{$query}";
    }
}
```

- [ ] **Step 4: Update the controller to verify before saving**

In `app/Http/Controllers/Settings/ChannelAccountController.php`, import the verifier:

```php
use App\Services\Omnichannel\SmtpImapCredentialVerifier;
```

Change the `store` signature:

```php
public function store(Request $request, SmtpImapCredentialVerifier $verifier)
```

Inside `store`, before `ChannelAccount::updateOrCreate(...)`, build and verify the config:

```php
$configuration = [
    'imap_host' => $validated['imap_host'],
    'imap_port' => (int) $validated['imap_port'],
    'imap_encryption' => $validated['imap_encryption'],
    'imap_username' => $validated['imap_username'],
    'imap_password' => $validated['imap_password'],
    'smtp_host' => $validated['smtp_host'],
    'smtp_port' => (int) $validated['smtp_port'],
    'smtp_encryption' => $validated['smtp_encryption'],
    'smtp_username' => $validated['smtp_username'],
    'smtp_password' => $validated['smtp_password'],
    'email_address' => $validated['email_address'],
];

$verifier->verify($configuration);
```

Then use the variable in the save payload:

```php
'configuration' => $configuration,
```

- [ ] **Step 5: Run tests to verify they pass**

Run:

```bash
php artisan test tests/Feature/Settings/PrivateMailboxConnectionTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Omnichannel/SmtpImapCredentialVerifier.php app/Http/Controllers/Settings/ChannelAccountController.php tests/Feature/Settings/PrivateMailboxConnectionTest.php
git commit -m "feat: verify private mailbox credentials before saving"
```

---

### Task 2: Fix Sync Status So Failed IMAP Does Not Become Active

**Files:**
- Modify: `app/Jobs/SyncChannelAccountJob.php`
- Modify: `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`
- Test: `tests/Unit/Omnichannel/SyncChannelAccountJobTest.php`

- [ ] **Step 1: Write failing job status tests**

Create `tests/Unit/Omnichannel/SyncChannelAccountJobTest.php`:

```php
<?php

use App\Contracts\Omnichannel\MailboxProvider;
use App\Jobs\SyncChannelAccountJob;
use App\Models\ChannelAccount;
use App\Models\User;
use App\Services\Omnichannel\Drivers\SmtpImapProvider;

it('marks account active when provider sync succeeds', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'sales@example.test',
        'configuration' => [],
        'sync_status' => 'idle',
    ]);

    app()->instance(SmtpImapProvider::class, new class implements MailboxProvider {
        public function syncInbound(ChannelAccount $account): array
        {
            return ['synced' => 1, 'errors' => 0, 'status' => 'success'];
        }

        public function sendOutgoing(\App\Models\EmailMessage $message): bool { return true; }
        public function downloadAttachment(\App\Models\EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(\App\Models\EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(\App\Models\EmailMessage $message): array { return []; }
    });

    (new SyncChannelAccountJob($account->id))->handle();

    expect($account->fresh()->sync_status)->toBe('active');
    expect($account->fresh()->sync_error)->toBeNull();
});

it('marks account error when provider sync returns an error result', function () {
    $company = User::factory()->create(['type' => 'company']);

    $account = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'sales@example.test',
        'configuration' => [],
        'sync_status' => 'idle',
    ]);

    app()->instance(SmtpImapProvider::class, new class implements MailboxProvider {
        public function syncInbound(ChannelAccount $account): array
        {
            return ['synced' => 0, 'errors' => 1, 'status' => 'error', 'message' => 'IMAP login failed'];
        }

        public function sendOutgoing(\App\Models\EmailMessage $message): bool { return false; }
        public function downloadAttachment(\App\Models\EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(\App\Models\EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(\App\Models\EmailMessage $message): array { return []; }
    });

    (new SyncChannelAccountJob($account->id))->handle();

    expect($account->fresh()->sync_status)->toBe('error');
    expect($account->fresh()->sync_error)->toBe('IMAP login failed');
});
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
php artisan test tests/Unit/Omnichannel/SyncChannelAccountJobTest.php
```

Expected: second test FAILS because the job sets `sync_status` to `active`.

- [ ] **Step 3: Update the sync job**

In `app/Jobs/SyncChannelAccountJob.php`, replace the success update block after `$stats = $driver->syncInbound($account);` with:

```php
if (($stats['status'] ?? 'success') === 'error') {
    $account->update([
        'sync_status' => 'error',
        'sync_error' => $stats['message'] ?? 'Mailbox sync failed.',
        'last_sync_at' => now(),
    ]);

    Log::warning('Channel sync completed with provider error', [
        'channel_account_id' => $this->channelAccountId,
        'message' => $stats['message'] ?? null,
    ]);

    return;
}

$account->update([
    'sync_status' => 'active',
    'sync_error' => null,
    'last_sync_at' => now(),
]);
```

- [ ] **Step 4: Make provider sync results consistent**

In `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`, when INBOX is not found, also update the account error:

```php
if (!$inbox) {
    $message = 'INBOX folder not found';
    $account->update(['sync_status' => 'error', 'sync_error' => $message]);

    return ['synced' => 0, 'errors' => 1, 'status' => 'error', 'message' => $message];
}
```

In the catch block, include `sync_error`:

```php
$account->update([
    'sync_status' => 'error',
    'sync_error' => $e->getMessage(),
]);
```

- [ ] **Step 5: Run sync tests**

Run:

```bash
php artisan test tests/Unit/Omnichannel/SyncChannelAccountJobTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/SyncChannelAccountJob.php app/Services/Omnichannel/Drivers/SmtpImapProvider.php tests/Unit/Omnichannel/SyncChannelAccountJobTest.php
git commit -m "fix: preserve private mailbox sync errors"
```

---

### Task 3: Add Mailbox Selection for New Compose

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `resources/js/pages/conversations/index.tsx`
- Test: `tests/Feature/ConversationPrivateMailboxTest.php`

- [ ] **Step 1: Write failing compose test for selected private mailbox**

Create `tests/Feature/ConversationPrivateMailboxTest.php`:

```php
<?php

use App\Contracts\Omnichannel\MailboxProvider;
use App\Models\ChannelAccount;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Omnichannel\Drivers\SmtpImapProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use App\Http\Controllers\ConversationController;

it('composes new email using the selected private mailbox account', function () {
    Queue::fake();

    $company = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);

    ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'gmail',
        'email_address' => 'gmail@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'sales@example.test',
        'configuration' => [
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sales@example.test',
            'smtp_password' => 'secret',
        ],
        'sync_status' => 'active',
    ]);

    $capturedMessage = null;

    app()->instance(SmtpImapProvider::class, new class($capturedMessage) implements MailboxProvider {
        public function __construct(private mixed &$capturedMessage) {}
        public function syncInbound(ChannelAccount $account): array { return ['status' => 'success', 'synced' => 0, 'errors' => 0]; }
        public function sendOutgoing(EmailMessage $message): bool
        {
            $this->capturedMessage = $message->fresh('thread.channelAccount');
            return true;
        }
        public function downloadAttachment(EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(EmailMessage $message): array { return []; }
    });

    $this->actingAs($company);

    $request = Request::create('/api/conversations/compose', 'POST', [
        'channel_account_id' => $private->id,
        'to' => 'client@example.test',
        'subject' => 'Private mailbox test',
        'body' => '<p>Hello</p>',
    ]);

    $response = app(ConversationController::class)->compose($request);

    expect($response->getStatusCode())->toBe(200);
    expect($capturedMessage)->toBeInstanceOf(EmailMessage::class);
    expect($capturedMessage->from_email)->toBe('sales@example.test');
    expect($capturedMessage->thread->channel_account_id)->toBe($private->id);
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationPrivateMailboxTest.php
```

Expected: FAIL because `compose()` ignores `channel_account_id`.

- [ ] **Step 3: Update `ConversationController@index` to pass all accounts**

In `ConversationController@index`, add:

```php
$channelAccounts = ChannelAccount::where('user_id', $companyId)
    ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")
    ->orderBy('created_at', 'desc')
    ->get(['id', 'type', 'email_address', 'sync_status', 'sync_error', 'last_sync_at']);
```

Then include this in the Inertia payload:

```php
'channelAccounts' => $channelAccounts->map(fn (ChannelAccount $account) => [
    'id' => $account->id,
    'type' => $account->type,
    'email' => $account->email_address,
    'sync_status' => $account->sync_status,
    'sync_error' => $account->sync_error,
    'last_sync_at' => $account->last_sync_at?->toIso8601String(),
])->values(),
```

- [ ] **Step 4: Update `compose()` validation and account lookup**

In `ConversationController@compose`, add this validation key:

```php
'channel_account_id' => 'nullable|integer|exists:channel_accounts,id',
```

Replace the account lookup with:

```php
$accountQuery = \App\Models\ChannelAccount::where('user_id', $companyId);

if ($request->filled('channel_account_id')) {
    $accountQuery->where('id', (int) $request->channel_account_id);
}

$account = $accountQuery
    ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")
    ->orderBy('created_at', 'desc')
    ->first();

if (!$account) {
    return response()->json(['error' => 'No connected mailbox account found.'], 422);
}

if ($account->sync_status !== 'active') {
    return response()->json(['error' => 'Selected mailbox is not active. Please reconnect or sync it first.'], 422);
}
```

- [ ] **Step 5: Update conversations frontend props**

In `resources/js/pages/conversations/index.tsx`, update the component signature:

```tsx
export default function ConversationsIndex({
    channelAccount,
    channelAccounts = [],
    companyId,
    isOwner,
    unreadCount: initialUnreadCount,
    selectedThreadId,
}: {
    channelAccount: any;
    channelAccounts?: any[];
    companyId: number;
    isOwner: boolean;
    unreadCount?: number;
    selectedThreadId?: number | null;
}) {
```

Add state near compose fields:

```tsx
const [composeChannelAccountId, setComposeChannelAccountId] = useState<string>(channelAccount?.id?.toString() || '');
```

When building `formData` in `handleSendNewEmail`, add:

```tsx
if (composeChannelAccountId) {
    formData.append('channel_account_id', composeChannelAccountId);
}
```

- [ ] **Step 6: Add From selector in the compose modal**

Inside the compose modal before the `To` field, add:

```tsx
<div className="grid gap-2">
    <Label className="text-xs">{t('From')}</Label>
    <select
        value={composeChannelAccountId}
        onChange={(event) => setComposeChannelAccountId(event.target.value)}
        className="w-full flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
    >
        {channelAccounts.map((account: any) => (
            <option key={account.id} value={account.id} disabled={account.sync_status !== 'active'}>
                {account.email} ({account.type === 'gmail' ? 'Gmail' : 'IMAP/SMTP'})
                {account.sync_status !== 'active' ? ' - inactive' : ''}
            </option>
        ))}
    </select>
</div>
```

- [ ] **Step 7: Run compose tests**

Run:

```bash
php artisan test tests/Feature/ConversationComposeTest.php tests/Feature/ConversationPrivateMailboxTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ConversationController.php resources/js/pages/conversations/index.tsx tests/Feature/ConversationPrivateMailboxTest.php
git commit -m "feat: choose mailbox when composing conversations"
```

---

### Task 4: Fix Background Inbox Sync Endpoint

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Test: `tests/Feature/ConversationPrivateMailboxTest.php`

- [ ] **Step 1: Add failing test for sync endpoint with private mailbox**

Append to `tests/Feature/ConversationPrivateMailboxTest.php`:

```php
it('syncs inbox history through the active channel account instead of the legacy gmail account', function () {
    $company = User::factory()->create([
        'type' => 'company',
        'email_verified_at' => now(),
    ]);

    $private = ChannelAccount::query()->create([
        'user_id' => $company->id,
        'type' => 'smtp_imap',
        'email_address' => 'sales@example.test',
        'configuration' => [],
        'sync_status' => 'active',
    ]);

    app()->instance(SmtpImapProvider::class, new class implements MailboxProvider {
        public function syncInbound(ChannelAccount $account): array
        {
            expect($account->email_address)->toBe('sales@example.test');
            return ['status' => 'success', 'synced' => 2, 'errors' => 0];
        }
        public function sendOutgoing(EmailMessage $message): bool { return true; }
        public function downloadAttachment(EmailMessage $message, string $attachmentId): string { return ''; }
        public function updateThreadStatus(EmailThread $thread, string $status): bool { return true; }
        public function getLiveAttachments(EmailMessage $message): array { return []; }
    });

    $this->actingAs($company)
        ->postJson(route('api.conversations.sync_inbox_more'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('stats.synced', 2);
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/ConversationPrivateMailboxTest.php
```

Expected: FAIL because `syncInboxHistory()` still checks `GmailAccount` and uses undefined `$channelAccount`.

- [ ] **Step 3: Replace legacy Gmail lookup in `syncInboxHistory()`**

In `ConversationController@syncInboxHistory`, replace the method body account lookup with:

```php
$companyId = auth()->user()->creatorId();

$channelAccount = ChannelAccount::where('user_id', $companyId)
    ->orderByRaw("CASE WHEN sync_status = 'active' THEN 0 ELSE 1 END")
    ->orderBy('created_at', 'desc')
    ->first();

if (!$channelAccount) {
    return response()->json(['error' => 'No mailbox account connected'], 400);
}
```

Keep the existing `MailboxManager::resolve($channelAccount)` block, but change `next_page_token` to be Gmail-only:

```php
'next_page_token' => $channelAccount->type === 'gmail'
    ? $channelAccount->fresh()->getConfig('next_page_token')
    : null,
```

- [ ] **Step 4: Run sync endpoint tests**

Run:

```bash
php artisan test tests/Feature/ConversationPrivateMailboxTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ConversationController.php tests/Feature/ConversationPrivateMailboxTest.php
git commit -m "fix: sync inbox history through channel accounts"
```

---

### Task 5: Make IMAP Sync Safer Against Duplicates

**Files:**
- Modify: `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`
- Test: add focused unit or feature coverage if message construction helpers already exist; otherwise cover with method-level extracted helper.

- [ ] **Step 1: Extract duplicate lookup into a protected method**

In `SmtpImapProvider`, add:

```php
protected function messageAlreadySynced(ChannelAccount $account, string|int|null $uid): bool
{
    if ($uid === null || $uid === '') {
        return false;
    }

    return EmailMessage::query()
        ->where('external_message_id', (string) $uid)
        ->whereHas('thread', function ($query) use ($account) {
            $query->where('channel_account_id', $account->id);
        })
        ->exists();
}
```

- [ ] **Step 2: Use scoped duplicate check**

Replace:

```php
if (EmailMessage::where('external_message_id', $uid)->exists()) {
    return;
}
```

with:

```php
if ($this->messageAlreadySynced($account, $uid)) {
    return;
}
```

- [ ] **Step 3: Add a regression test if practical**

If `processInboundMessage()` is tested through fake IMAP messages, assert that the same UID in different `ChannelAccount` rows is not treated as duplicate. If fake Webklex messages become too brittle, keep this change with a code-review note and cover it through a higher-level integration test in Task 8.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Omnichannel/Drivers/SmtpImapProvider.php
git commit -m "fix: scope imap duplicate detection by mailbox"
```

---

### Task 6: Send Attachments Through Private SMTP

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `app/Services/Omnichannel/Drivers/SmtpImapProvider.php`
- Test: `tests/Feature/ConversationPrivateMailboxTest.php`

- [ ] **Step 1: Add attachment metadata to `EmailMessage` before send**

In `ConversationController@compose` and `ConversationController@reply`, after creating the `EmailMessage` and before `$driver->sendOutgoing($message)`, store uploaded files:

```php
if (!empty($attachments)) {
    $storedAttachments = [];

    foreach ($attachments as $attachment) {
        $path = $attachment->store("outbox_attachments/{$message->id}");
        $storedAttachments[] = [
            'path' => $path,
            'name' => $attachment->getClientOriginalName(),
            'mime' => $attachment->getClientMimeType(),
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

- [ ] **Step 2: Attach files in `SmtpImapProvider::sendOutgoing()`**

After CC/BCC handling, add:

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

- [ ] **Step 3: Add feature test with fake upload**

Append to `tests/Feature/ConversationPrivateMailboxTest.php` a test that posts to compose with `UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf')`, stubs `SmtpImapProvider`, and asserts `$message->metadata['outgoing_attachments'][0]['name']` is `quote.pdf`.

- [ ] **Step 4: Run tests**

Run:

```bash
php artisan test tests/Feature/ConversationPrivateMailboxTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ConversationController.php app/Services/Omnichannel/Drivers/SmtpImapProvider.php tests/Feature/ConversationPrivateMailboxTest.php
git commit -m "feat: send private mailbox attachments"
```

---

### Task 7: Fix Frontend Copy and Status Behavior

**Files:**
- Modify: `resources/js/pages/settings/components/mailbox-settings.tsx`
- Modify: `resources/js/pages/conversations/index.tsx`

- [ ] **Step 1: Remove misleading “same as IMAP if empty” placeholders**

In `mailbox-settings.tsx`, replace:

```tsx
placeholder={t('(Same as IMAP if empty)')}
```

with:

```tsx
placeholder="you@company.com"
```

for SMTP username, and:

```tsx
placeholder={t('Required')}
```

for SMTP password.

- [ ] **Step 2: Disable connect until required fields are present**

Add a derived boolean near form state:

```tsx
const canSubmitPrivateMailbox = [
    data.email_address,
    data.imap_host,
    data.imap_port,
    data.imap_username,
    data.imap_password,
    data.smtp_host,
    data.smtp_port,
    data.smtp_username,
    data.smtp_password,
].every((value) => String(value).trim().length > 0);
```

Change submit button:

```tsx
<Button type="submit" disabled={processing || !canSubmitPrivateMailbox}>
```

- [ ] **Step 3: Show clearer statuses**

Replace the fallback status rendering so `idle` is not shown as `Error`:

```tsx
{account.sync_status === 'active' ? (
    <span className="flex items-center gap-1 text-green-600">
        <CheckCircle2 className="h-3 w-3" /> {t('Active')}
    </span>
) : account.sync_status === 'syncing' ? (
    <span className="flex items-center gap-1 text-primary">
        <RefreshCw className="h-3 w-3 animate-spin" /> {t('Syncing...')}
    </span>
) : account.sync_status === 'error' ? (
    <span className="flex items-center gap-1 text-red-500">
        <AlertCircle className="h-3 w-3" /> {t('Error')}
    </span>
) : (
    <span className="flex items-center gap-1 text-muted-foreground">
        <AlertCircle className="h-3 w-3" /> {t('Not synced yet')}
    </span>
)}
```

- [ ] **Step 4: Run frontend build**

Run:

```bash
npm run build
```

Expected: build exits 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/settings/components/mailbox-settings.tsx resources/js/pages/conversations/index.tsx
git commit -m "fix: clarify private mailbox frontend states"
```

---

### Task 8: End-to-End Manual Verification Checklist

**Files:**
- No code changes unless a checklist item fails.

- [ ] **Step 1: Run migrations**

Run:

```bash
php artisan migrate
```

Expected: migrations complete without errors.

- [ ] **Step 2: Run queue worker**

Run in a separate terminal:

```bash
php artisan queue:work
```

Expected: worker starts and waits for jobs.

- [ ] **Step 3: Start the app**

Run:

```bash
php artisan serve
npm run dev
```

Expected: Laravel and Vite dev servers start.

- [ ] **Step 4: Connect private mailbox**

In the app:

1. Go to Settings.
2. Open Mailboxes & Omnichannel Inbox.
3. Click Connect Mailbox.
4. Choose Private Email (IMAP/SMTP).
5. Fill all IMAP and SMTP fields.
6. Click Verify & Connect.

Expected: invalid credentials are rejected before saving; valid credentials save and queue initial sync.

- [ ] **Step 5: Verify sync**

In the app:

1. Wait for queue worker to process the sync job.
2. Refresh Settings.
3. Confirm mailbox status is Active.
4. Open Conversations.
5. Confirm synced messages appear.

Expected: status is Active only when sync succeeds; errors show useful error text.

- [ ] **Step 6: Verify selected mailbox sending**

In Conversations:

1. Click Compose.
2. Choose the private mailbox in From.
3. Send an email to a test inbox.
4. Confirm the received email From address is the private mailbox address.

Expected: email sends through SMTP and arrives from the selected private mailbox.

- [ ] **Step 7: Verify reply behavior**

In Conversations:

1. Open a thread synced from the private mailbox.
2. Send a reply.
3. Confirm the reply arrives from the same private mailbox.

Expected: replies use `thread.channelAccount`, not the first connected account.

- [ ] **Step 8: Run full verification**

Run:

```bash
php artisan test tests/Feature/Settings/PrivateMailboxConnectionTest.php tests/Feature/ConversationComposeTest.php tests/Feature/ConversationPrivateMailboxTest.php tests/Unit/Omnichannel/SyncChannelAccountJobTest.php
npm run build
```

Expected: all tests pass and frontend build exits 0.

- [ ] **Step 9: Commit final verification notes if docs are updated**

If a short usage doc is added, commit it:

```bash
git add docs
git commit -m "docs: document private mailbox usage"
```

---

## User-Facing Usage After Implementation

1. Go to Settings -> Mailboxes & Omnichannel Inbox.
2. Click Connect Mailbox -> Private Email (IMAP/SMTP).
3. Enter IMAP host, port, encryption, username, and password.
4. Enter SMTP host, port, encryption, username, and password.
5. Click Verify & Connect.
6. Keep `php artisan queue:work` running for background sync.
7. In Conversations -> Compose, select the mailbox from the From dropdown.
8. Replies automatically use the mailbox that owns the thread.

## Self-Review

- Spec coverage: This plan covers verification before saving, accurate sync status, compose mailbox selection, private mailbox sync endpoint, duplicate safety, outgoing attachments, frontend copy, and manual verification.
- Placeholder scan: No `TBD`, open-ended “handle edge cases”, or unexplained “write tests” steps remain.
- Type consistency: The plan consistently uses `channel_account_id`, `ChannelAccount`, `SmtpImapProvider`, and `SmtpImapCredentialVerifier`.
