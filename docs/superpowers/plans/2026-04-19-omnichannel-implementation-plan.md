# Implementation Plan: Omnichannel Shared Inbox Refactoring

Refactor the existing Gmail-centric shared inbox system into a provider-agnostic "Omnichannel" architecture. This enables multiple mailbox connections (Gmail, IMAP/SMTP) per company and provides a scalable foundation for future integrations.

## User Review Required

> [!IMPORTANT]
> The migration will be **additive**. We will create new tables and columns while preserving the old ones to ensure zero downtime. Once the new system is fully verified, we will perform a cutover.

> [!WARNING]
> This refactor involves core database changes to `email_threads` and `email_messages`. Direct references to `gmail_account_id` in external services (like AI triage or automation) will need to be updated.

## Proposed Changes

### Phase 1: Database Schema (Additive)
Create the foundation for generic channel accounts and provider-agnostic threading.

#### [NEW] [create_channel_accounts_table.php](file:///c:/Users/Rovick/Downloads/Final_Production/database/migrations/2026_04_19_000001_create_channel_accounts_table.php)
- Stores connection details for Gmail, IMAP, and SMTP.
- Fields: `type`, `email_address`, `credentials` (encrypted JSON), `sync_status`, `last_sync_at`.

#### [NEW] [update_email_tables_for_omnichannel.php](file:///c:/Users/Rovick/Downloads/Final_Production/database/migrations/2026_04_19_000002_update_email_tables_for_omnichannel.php)
- Add `channel_account_id` to `email_threads`.
- Add `external_thread_id` and `external_message_id` (generic alternatives to `gmail_thread_id`).
- Add `channel_type` (gmail, imap, etc.) for easier filtering.

---

### Phase 2: Core Abstraction Layer
Introduce the Interface and Manager pattern.

#### [NEW] [MailboxProvider.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Contracts/Omnichannel/MailboxProvider.php)
- Define the standard contract for all providers: `sync()`, `send()`, `fetchAttachment()`.

#### [NEW] [MailboxManager.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Services/Omnichannel/MailboxManager.php)
- Factory to resolve the correct driver based on `ChannelAccount->type`.

#### [NEW] [GmailProvider.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Services/Omnichannel/Drivers/GmailProvider.php)
- Wraps existing `GmailService` logic into the `MailboxProvider` interface.

---

### Phase 3: Controller & Job Refactoring
Update the business logic to use the `MailboxManager`.

#### [MODIFY] [ConversationController.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Http/Controllers/ConversationController.php)
- Replace direct `GmailService` calls with `MailboxManager::resolve($account)`.

#### [NEW] [SyncChannelAccountJob.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Jobs/SyncChannelAccountJob.php)
- A generic job that handles synchronisation for ANY connected channel.

---

### Phase 4: IMAP & SMTP Implementation
Add the actual logic for custom email connections.

#### [MODIFY] [SmtpImapProvider.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Services/Omnichannel/Drivers/SmtpImapProvider.php)
- **Configuration Schema**: Expects `imap_*` and `smtp_*` keys in the encrypted `configuration` JSON.
- **`syncInbound()` logic**:
    - Connect to IMAP via `webklex/laravel-imap`.
    - Fetch messages using `UID` as the primary key.
    - **Threading**: Parse `In-Reply-To` and `References`. Lookup existing messages/threads by these headers to maintain omnichannel conversation continuity.
    - **Attachments**: Extract metadata (filename, size, content_id) for lazy-downloading.
- **`sendOutgoing()` logic**:
    - Build a dynamic SMTP transport using the account's credentials.
    - Ensure `Message-ID` is stored locally after sending.
    - Inject `References` headers when replying to maintain external threading.

---

### Phase 5: Attachment Caching Logic
Implement the lazy-download and local caching strategy.

#### [MODIFY] [SmtpImapProvider.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Services/Omnichannel/Drivers/SmtpImapProvider.php)
- Implement `downloadAttachment()`: logic to reconnect to IMAP and fetch a specific message part by its `part_number`.

#### [MODIFY] [ConversationController.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Http/Controllers/ConversationController.php)
- **`downloadAttachment` / `viewAttachment`**: 
    - Check local cache: `storage/app/private/inbox_attachments/{message_id}/{attachment_id}`.
    - If miss: call `$driver->downloadAttachment()`, save to local path, then serve.
    - Ensure strict company-level authorization check before serving any file.

#### [MODIFY] [SyncChannelAccountJob.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Jobs/SyncChannelAccountJob.php)
- Ensure attachment metadata (IDs, names, sizes) is stored in the `EmailMessage->metadata` column during the initial sync to enable lazy-loading.

## Open Questions

- **Migration of existing data**: Should we automatically backfill the new `channel_account_id` column for existing Gmail threads during Phase 1? (Recommended for consistency).
- **IMAP Library**: Shall we use `webklex/laravel-imap` (industry standard) or a lighter native implementation?

## Verification Plan

### Automated Tests
- `php artisan test --filter=Omnichannel`
- Mock provider tests to ensure the `MailboxManager` correctly handles driver switching.
- Threading consistency tests: Ensure replies from an SMTP account correctly thread with IMAP-synced messages.

### Manual Verification
- Connect a test IMAP account (e.g., Mailtrap or a private server).
- Verify that messages appear in the Shared Inbox alongside Gmail messages.
- Test "Reply" from the UI and verify the recipient receives it and it threads correctly.
- Click an attachment to verify lazy-caching to local storage.
