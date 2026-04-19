# Design Document: Omnichannel Shared Inbox Refactoring
**Date**: 2026-04-19
**Status**: Draft

## 1. Overview
The goal is to refactor the current Gmail-centric shared inbox system into a provider-agnostic "Omnichannel" architecture. This will enable the integration of custom IMAP/SMTP accounts while preserving existing Gmail functionality and maintaining a unified user experience.

## 2. Goals & Success Criteria
- Support multiple mailbox connections (Gmail, IMAP/SMTP) per company.
- Implement a provider abstraction layer (Driver pattern).
- Support cross-platform replies using standard email headers.
- Implement lazy/on-demand attachment caching with permanent local storage.
- Maintain company-wide visibility (everyone in the company sees all conversations).

## 3. Architecture

### 3.1 Provider Abstraction Layer
We will use an interface-based driver pattern:
- **`MailboxProviderInterface`**: Defines standard operations (`syncInbound()`, `sendOutgoing()`, `downloadAttachment()`, `updateThreadStatus()`).
- **`GmailProvider`**: Adapter for existing Gmail API logic.
- **`SmtpImapProvider`**: New provider for custom email connections.
- **`MailboxManager`**: Service factory that resolves the appropriate provider based on the account type.

### 3.2 Data Model Changes
- **`ChannelAccount`**: Generic table replacing/generalizing `gmail_accounts`.
- **`EmailThread`**: Decoupled from Gmail IDs; uses `channel_account_id` and `external_thread_id`.
- **`EmailMessage`**: Decoupled from Gmail IDs; uses `external_message_id`.
- **`AttachmentCache`**: New table for storing lazily-downloaded attachments.

## 4. Operational Flows

### 4.1 Synchronisation
- A single `SyncChannelAccountJob` will replace provider-specific jobs.
- It will delegate the actual sync logic to the resolved `MailboxProvider`.
- Gmail will continue using the History API (incremental), while IMAP will use UID-based polling.

### 4.2 Outbound Sending
- Replies and new messages will be routed through the `sendOutgoing()` method of the provider.
- Gmail sends via API; IMAP uses a dedicated SMTP connection.
- Standard email headers (`In-Reply-To`, `References`) will be used to ensure threading consistency across different platforms.

### 4.3 Attachments
- When a user views a message, the frontend requests attachment metadata.
- If the attachment is not in the local `AttachmentCache`, the system fetches it from the provider (Gmail API or IMAP server), stores it locally (Disk/S3), and serves it. Subsequent views use the local cache.

## 5. Security & Permissions
- All mail connections are scoped to the company (`created_by` relationship).
- All staff members of the company have view/reply access to all connected mailboxes within that company.
- Mailbox credentials (SMTP passwords, OAuth tokens) are stored in the `credentials` column of `channel_accounts`, encrypted at rest.

## 6. Testing & Rollout
- **Phase 1**: Unit test the `MailboxManager` and `MailboxProviderInterface`.
- **Phase 2**: Side-by-side testing of `GmailProvider` vs. original `GmailService`.
- **Phase 3**: Integration testing of IMAP connection and sync.
- **Phase 4**: Incremental rollout to companies using Gmail first, then enabling custom IMAP.
