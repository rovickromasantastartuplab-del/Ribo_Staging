# IMAP SMTP Shared Inbox Implementation Plan

## 1. Executive Summary

The current system is a Laravel 12 + Inertia React CRM with an existing shared-conversation feature built around Gmail, not a provider-agnostic mail platform. This is evidenced by dedicated Gmail service/controller/job/webhook code in `app/Services/GmailService.php`, `app/Http/Controllers/GmailController.php`, `app/Http/Controllers/GmailWebhookController.php`, `app/Jobs/SyncGmailThreadsJob.php`, and Google OAuth wiring in `app/Http/Controllers/Settings/SocialAuthController.php` plus `config/services.php`.

What exists today:
- Shared conversation UI and APIs over persisted email threads/messages.
- Gmail OAuth connection flow.
- Gmail API-based inbound sync.
- Gmail API-based outbound send and reply.
- Shared visibility at company scope via `created_by`.
- Assignment workflow via `email_thread_assignments`.
- Basic status/priority/follow-up workflow.
- Queue-backed Gmail sync and scheduled follow-up processing.
- Real-time sync completion broadcast via Pusher/Echo.

What is missing for a provider-agnostic IMAP + SMTP inbox:
- No IMAP code path was found.
- No SMTP-based shared inbox send pipeline was found.
- No provider abstraction layer was found for mailbox sync/send.
- Core schema uses Gmail-specific table names and identifier columns.
- Webhook and real-time sync lifecycle assume Gmail Pub/Sub and Gmail history IDs.
- Attachment access is proxied from Gmail API rather than stored provider-independently.

Assessment:
- The current architecture supports a Gmail-based shared inbox.
- It does not currently support provider-agnostic IMAP + SMTP without substantial refactoring across schema, services, controllers, jobs, UI types, and operational flows.

## 2. Evidence-Based Findings

### Observed

- Backend framework is Laravel 12 with React/Inertia frontend.
  - Evidence: `composer.json`, `package.json`.
- Authentication uses Laravel auth with verified users plus Spatie roles/permissions.
  - Evidence: `app/Models/User.php`, `database/migrations/2025_05_25_000000_create_permission_tables.php`, `routes/auth.php`.
- Company/workspace boundary is modeled through `users.type` and `users.created_by`, not through dedicated team/workspace tables.
  - Evidence: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `app/Traits/AutoApplyPermissionCheck.php`.
- Shared inbox data model exists:
  - `gmail_accounts`
  - `email_threads`
  - `email_messages`
  - `email_thread_assignments`
  - `email_threadables`
  - `gmail_account_activities`
  - `thread_follow_up_stages`
  - `thread_follow_up_queue`
  - `email_open_logs`
  - `email_click_logs`
  - Evidence: migrations dated `2026_03_17` through `2026_03_31`.
- Inbound sync is Gmail API-based and queue-backed.
  - Evidence: `app/Services/GmailService.php`, `app/Jobs/SyncGmailThreadsJob.php`.
- Outbound sending is Gmail API-based.
  - Evidence: `app/Services/GmailService.php` `sendMessage()`, `app/Http/Controllers/ConversationController.php` `compose()` and `reply()`.
- Gmail OAuth and scopes are implemented with Socialite + Google API client.
  - Evidence: `app/Http/Controllers/Settings/SocialAuthController.php`, `config/services.php`, `composer.json`.
- Real-time UI update capability exists via Pusher/Echo.
  - Evidence: `app/Events/GmailSyncCompleted.php`, `routes/channels.php`, `resources/js/utils/echo.ts`.
- Global SMTP configuration exists for application mail and notifications.
  - Evidence: `config/mail.php`, `app/Services/MailConfigService.php`, `app/Http/Controllers/Settings/EmailSettingController.php`.

### Missing

- No IMAP implementation found.
  - Evidence: no IMAP matches in `app`, `config`, `database`, or `routes`.
- No provider-neutral mailbox account table found.
  - Evidence: only `gmail_accounts` exists; no generic `email_accounts` table found.
- No provider abstraction interface/contract for inbox sync or sending found.
  - Evidence: conversation flows directly instantiate `GmailService`.
- No provider-neutral message/thread identifiers found.
  - Evidence: `gmail_thread_id`, `gmail_message_id`, `gmail_labels`, `last_history_id`, `google_id`, `gmail_address`.
- No inbound SMTP/MX or generic webhook ingestion path for email found.
  - Evidence: public inbox webhook route is only `api/webhooks/gmail`; no mail-receiver route found.
- No click-tracking controller or route found despite `email_click_logs` table existing.
  - Evidence: `database/migrations/2026_03_31_000004_create_email_click_logs_table.php`; no controller usage found for `EmailClickLog`.

### Risk

- Schema is tightly coupled to Gmail, so introducing IMAP + SMTP without a schema strategy will create dual models or brittle aliases.
- Controllers and jobs directly depend on Gmail classes, so provider support cannot be added incrementally without an abstraction seam.
- Attachment retrieval depends on live Gmail API calls, which will not work for IMAP providers.
- Sync lifecycle depends on Gmail `historyId` and Pub/Sub watch renewal, which has no equivalent in plain IMAP polling.
- Current company-scoped visibility is broad; per-inbox or per-mailbox access controls are not modeled.
- Reply permission comments and enforcement are not fully aligned.
  - Evidence: `reply-conversations` comment says staff must be assigned, but `ConversationController::reply()` checks permission only.
- `email_thread_assignments` migration lacks foreign keys and uniqueness constraints.
  - Evidence: `database/migrations/2026_03_21_170001_create_email_thread_assignments_table.php`.

### Needs confirmation

- Whether product intent requires multiple mailboxes per company simultaneously.
  - Evidence: current Gmail lookup usually fetches first company Gmail account only.
- Whether drafts/forwards are required beyond compose/reply.
  - Evidence: compose and reply exist; no forward/draft persistence found.
- Whether per-mailbox visibility rules are needed, or company-wide visibility is sufficient.
  - Evidence: current authorization is company scoped via `created_by`.
- Whether message body normalization/parsing requirements include provider-specific MIME edge cases beyond current Gmail implementation.
  - Evidence: parser currently reads Gmail payload parts only.

## 3. Gmail-Specific Coupling Analysis

### Gmail-specific code paths

- `app/Services/GmailService.php`
  - Uses `Google\Client` and `Google\Service\Gmail`.
  - Calls `users_threads`, `users_messages`, `users_history`, `users_messages_attachments`.
  - Uses Gmail watch, label modification, history-based incremental sync, Gmail thread/message IDs, Gmail labels.
- `app/Http/Controllers/GmailController.php`
  - Reads and manages only `GmailAccount`.
- `app/Http/Controllers/GmailWebhookController.php`
  - Decodes Google Pub/Sub webhook payloads and dispatches Gmail sync.
- `app/Jobs/SyncGmailThreadsJob.php`
  - Loads `GmailAccount` and executes `GmailService`.
- `app/Console/Commands/RenewGmailWatch.php`
  - Renews Gmail Pub/Sub watch for all connected Gmail accounts.
- `app/Events/GmailSyncCompleted.php`
  - Broadcast event name is Gmail-specific.

### Gmail-specific schema assumptions

- `gmail_accounts` table name is provider-specific.
- `email_threads.gmail_account_id` ties threads only to Gmail accounts.
- `email_threads.gmail_thread_id` assumes provider thread ID exists and is Gmail-defined.
- `email_messages.gmail_message_id` assumes provider message ID is Gmail-defined.
- `email_messages.gmail_labels` and `email_threads.labels` persist Gmail label IDs.
- `gmail_accounts.last_history_id` stores Gmail incremental sync cursor.
- `gmail_accounts.next_page_token` stores Gmail pagination token.
- `gmail_accounts.google_id` stores Google account identity.
- `gmail_account_activities` table is Gmail-specific.
- `thread_follow_up_queue.gmail_thread_id` and `gmail_message_id` hard-code Gmail identifiers into automation state.
- `email_open_logs.gmail_message_id` and `email_click_logs.gmail_message_id` hard-code Gmail identifiers into tracking.

### Gmail OAuth/auth flows

- Google OAuth is configured through Socialite.
- Requested scopes are:
  - `https://www.googleapis.com/auth/gmail.readonly`
  - `https://www.googleapis.com/auth/gmail.send`
  - `openid`
  - `email`
  - `profile`
- Access type is forced to `offline` with `prompt=consent`.
- Credentials are loaded from settings/config under Google-specific keys.
- Evidence: `app/Http/Controllers/Settings/SocialAuthController.php`, `config/services.php`.

### Gmail-based sync logic

- Initial sync uses Gmail thread listing plus per-thread fetch.
- Incremental sync uses Gmail history API and stored `last_history_id`.
- Real-time sync uses Gmail Pub/Sub watch + webhook + renewal scheduler.
- Sync filtering uses Gmail categories/labels.
- Evidence: `app/Services/GmailService.php`, `app/Http/Controllers/GmailWebhookController.php`, `app/Console/Commands/RenewGmailWatch.php`, `routes/console.php`.

### Gmail-based sending logic

- Compose and reply call `GmailService::sendMessage()`.
- Raw RFC 2822 payloads are sent through Gmail API, not SMTP.
- Archive/unarchive manipulates Gmail labels via thread modify endpoint.
- Evidence: `app/Services/GmailService.php`, `app/Http/Controllers/ConversationController.php`.

### Tight coupling points

- Controller-to-provider coupling: `ConversationController` and `GmailController` instantiate `GmailService` directly.
- Job-to-provider coupling: `SyncGmailThreadsJob` is Gmail-only.
- Webhook coupling: public inbound sync trigger is Gmail-only.
- Schema coupling: Gmail-specific keys in every mail-domain table.
- UI coupling:
  - Gmail settings panel and routes.
  - Frontend types named `GmailAccount`.
  - Gmail-specific copy in settings and conversation screens.
  - Evidence: `resources/js/pages/settings/components/integrations-settings.tsx`, `resources/js/types/conversations.ts`.

### Missing abstraction layers

- No `MailboxProvider` / `InboundSyncProvider` / `OutboundMailProvider` abstraction found.
- No provider-neutral mailbox repository/service found.
- No provider-neutral sync cursor abstraction found.
- No provider-neutral attachment fetch abstraction found.
- No provider-neutral mailbox credential model found.

## 4. Current Architecture Assessment

| Capability | Status | Evidence |
|---|---|---|
| Multi-account inbox | Partially supported | Schema supports multiple `gmail_accounts` rows overall, but company flows usually call `first()` and assume one Gmail account per company. |
| Shared visibility | Supported | Threads/messages scoped by `created_by`; all company staff can access company records. |
| IMAP sync lifecycle | Not supported | No IMAP code found. |
| SMTP sending | Partially supported | Global SMTP exists for app mail, but conversations send via Gmail API, not SMTP. |
| Threading | Partially supported | Thread model exists, but identifiers and reply mechanics are Gmail-based. |
| Assignment | Supported | `email_thread_assignments` plus controller/UI support. |
| Read/unread state | Partially supported | `is_read` exists locally; Gmail unread state is inferred from Gmail labels during sync. |
| Tagging | Partially supported | Gmail labels are persisted; no provider-neutral tagging model found. |
| Audit logs | Partially supported | `gmail_account_activities` exists, but it is Gmail-scoped rather than inbox-platform scoped. |
| Retries/failure recovery | Partially supported | Queue retries and sync error fields exist; no generalized mailbox retry framework found. |

## 5. Target Capability Scope

| Feature | Status | Evidence |
|---|---|---|
| Account connection | Partial | Gmail OAuth exists; no IMAP/SMTP account onboarding exists. |
| Mailbox sync | Partial | Gmail API sync exists; no IMAP sync exists. |
| Inbound ingestion | Partial | Gmail API + Pub/Sub exists; no provider-agnostic ingestion exists. |
| Outbound sending | Partial | Gmail API send exists; no shared inbox SMTP send exists. |
| Drafts / replies / forwards | Partial | Replies and new compose exist; drafts and forwards not found. |
| Assignment | Exists | Thread assignment table and API exist. |
| Visibility rules | Partial | Company-wide shared visibility exists; mailbox-level visibility rules not found. |
| Tagging / status | Partial | Local `status`/`priority` plus Gmail labels exist; provider-neutral folders/tags not found. |
| Audit history | Partial | Gmail account activity exists; provider-neutral inbox audit history not found. |
| Retries / failures | Partial | Queue retries, sync status, sync error exist; broader send/sync failure orchestration is limited. |
| Attachments | Partial | Gmail attachment metadata/download exists; local provider-neutral attachment ingestion/storage is disabled. |

## 6. Data Model Impact

### Reusable tables

- `email_threads`
  - Why needed: already represents conversation-level shared inbox entity.
  - Evidence: `database/migrations/2026_03_17_000002_create_email_threads_table.php`.
  - Dependencies: must be decoupled from `gmail_account_id` and `gmail_thread_id`.
- `email_messages`
  - Why needed: already represents persisted messages with sender/recipient/body/timestamps.
  - Evidence: `database/migrations/2026_03_17_000003_create_email_messages_table.php`.
  - Dependencies: must be decoupled from `gmail_message_id` and `gmail_labels`.
- `email_thread_assignments`
  - Why needed: already supports shared inbox assignment workflow.
  - Evidence: `database/migrations/2026_03_21_170001_create_email_thread_assignments_table.php`.
  - Dependencies: needs constraint hardening.
- `email_threadables`
  - Why needed: already links threads to CRM records.
  - Evidence: `database/migrations/2026_03_18_053319_create_email_threadables_table.php`.
- `thread_follow_up_stages`, `thread_follow_up_queue`
  - Why needed: existing follow-up automation can be reused if provider identifiers become generic.
  - Evidence: `database/migrations/2026_03_31_000001_create_thread_follow_up_stages_table.php`, `2026_03_31_000002_create_thread_follow_up_queue_table.php`.

### Required extensions

- Replace or generalize `gmail_accounts`.
  - Why needed: current connection record is Gmail-only.
  - Evidence: `gmail_accounts` schema and `GmailAccount` model.
  - Dependencies: OAuth onboarding, sync jobs, controllers, UI, follow-up queue, activities.
- Extend `email_threads` with provider-neutral mailbox linkage and external identifiers.
  - Why needed: current thread row stores Gmail account FK and Gmail thread ID only.
  - Evidence: `gmail_account_id`, `gmail_thread_id`.
  - Dependencies: sync engine, retrieval APIs, follow-up queue, archive/status sync.
- Extend `email_messages` with provider-neutral external identifiers and delivery/sync state.
  - Why needed: current model stores Gmail message ID and labels only.
  - Evidence: `gmail_message_id`, `gmail_labels`, no send-state columns.
  - Dependencies: SMTP send tracking, IMAP dedupe, retries.
- Extend activity/logging tables to provider-neutral naming.
  - Why needed: `gmail_account_activities` cannot represent non-Gmail inbox events cleanly.
  - Evidence: table/model names.

### New tables if necessary

- Provider-neutral mailbox/account table.
  - Why needed: current repo has no generic mailbox account entity.
  - Evidence: only `gmail_accounts` exists.
  - Dependencies: provider abstraction layer, onboarding, permission model.
- Provider credential/secrets reference table or structured credential columns.
  - Why needed: IMAP and SMTP need host/port/username/password/Auth mode/TLS parameters; current Gmail OAuth table cannot store them.
  - Evidence: no such table found.
  - Dependencies: secure secret storage policy.
- Provider-neutral sync cursor/state table if multiple cursors per mailbox/folder are needed.
  - Why needed: current single `last_history_id` model is Gmail-specific and insufficient for IMAP polling state.
  - Evidence: no IMAP sync state table found.
- Optional mailbox membership/visibility table if mailbox-level access differs from company-level access.
  - Why needed: current visibility is company-wide only.
  - Evidence: no mailbox membership table found.

### Indexes / constraints

- Add uniqueness and foreign keys on `email_thread_assignments`.
  - Why needed: current table allows duplicates and orphaned references.
  - Evidence: migration has indexes only.
- Add provider-neutral unique keys for external message and thread deduplication.
  - Why needed: IMAP sync needs idempotency against message identifiers/UIDs.
  - Evidence: current uniqueness is Gmail-based.
- Add indexes for mailbox/account + last_message_at + status retrieval.
  - Why needed: current thread listing is mailbox-centric and status-based.
  - Evidence: conversation listing queries in `ConversationController`.

### Migrations

- Migrate in additive phases first; do not remove Gmail columns until provider-neutral code is live and backfilled.
  - Why needed: current production logic still depends on Gmail-specific columns across controllers, jobs, and UI.
  - Evidence: direct column/model usage throughout mail flows.

## 7. Important Code Areas to Change

### Email provider abstraction

- Current code:
  - `app/Services/GmailService.php`
  - direct `new GmailService(...)` calls in controllers/jobs/commands.
- Required changes:
  - Introduce provider-neutral inbox service contracts for:
    - account connection lifecycle
    - inbound sync
    - outbound send/reply
    - attachment retrieval
    - archive/unarchive or mailbox flag changes
  - Keep Gmail as first implementation behind the abstraction.
- Risks:
  - High regression risk because current flows instantiate Gmail service directly.

### IMAP sync engine

- Current code:
  - None found.
- Required changes:
  - Add polling-based IMAP sync worker/service.
  - Add provider-neutral cursor and dedupe persistence.
  - Normalize IMAP folders/flags into local thread/message state.
- Risks:
  - New subsystem; no existing evidence of reusable IMAP code.

### SMTP pipeline

- Current code:
  - App-level SMTP config only in `config/mail.php`, `MailConfigService`, `EmailSettingController`.
  - Conversation send uses Gmail API.
- Required changes:
  - Create mailbox-scoped SMTP sender for conversations.
  - Persist send result/external identifiers.
  - Support compose/reply via mailbox credentials rather than global app mail config.
- Risks:
  - Existing SMTP config is global/system email, not mailbox-specific.

### Parser

- Current code:
  - Gmail payload parsing in `GmailService` (`extractHeader`, `extractBody`, attachment traversal).
- Required changes:
  - Extract message normalization/parsing into provider-neutral message importer.
  - Keep Gmail parser as one adapter; add IMAP MIME parser path.
- Risks:
  - MIME/body handling differences across providers and IMAP messages.

### Threading logic

- Current code:
  - Gmail thread IDs are authoritative.
  - Cross-client message threading partly relies on `Message-ID` header.
  - Evidence: `gmail_thread_id`, `message_id_header`, reply `In-Reply-To`.
- Required changes:
  - Move to provider-neutral local thread resolution based on headers plus mailbox context.
  - Preserve provider thread IDs as optional external references, not primary business identity.
- Risks:
  - Thread fragmentation or over-merging during migration.

### Attachments

- Current code:
  - Local attachment sync intentionally disabled.
  - Download is proxied from Gmail API.
  - Evidence: `GmailService::syncAttachments()` no-op; `ConversationController::downloadAttachment()`.
- Required changes:
  - Decide on provider-neutral attachment strategy:
    - store locally/object storage, or
    - maintain provider fetch adapters for each mailbox type.
  - IMAP support will require non-Gmail retrieval path.
- Risks:
  - Provider-specific download behavior and retention gaps.

### Background jobs

- Current code:
  - `SyncGmailThreadsJob`
  - `RenewGmailWatch`
  - `ProcessFollowUps`
- Required changes:
  - Replace Gmail-specific jobs/commands with provider-neutral mailbox sync scheduler and worker set.
  - Retain Gmail webhook optimization as provider-specific fast path.
- Risks:
  - Coexistence of polling and webhook sync during migration.

### APIs

- Current code:
  - `GmailController`, `ConversationController`, Gmail settings routes/UI.
- Required changes:
  - Replace Gmail-specific endpoints and payloads with mailbox/provider-neutral APIs.
  - Preserve backward compatibility until frontend is migrated.
- Risks:
  - UI and API contract drift because frontend types are also Gmail-specific.

### Permissions

- Current code:
  - Company-wide access via `created_by`.
  - Conversation permissions via Spatie.
- Required changes:
  - Decide whether permissions remain conversation-level company-wide or become mailbox-scoped.
  - If mailbox-scoped, add mailbox membership model and enforcement.
- Risks:
  - Permission leaks if mailbox-specific visibility is introduced without schema/API updates.

### Observability

- Current code:
  - Uses `Log` and `gmail_account_activities`.
  - Sync completion broadcast exists.
- Required changes:
  - Introduce provider-neutral activity/event taxonomy and failure telemetry.
  - Add sync/send metrics by provider and mailbox.
- Risks:
  - Limited troubleshooting if IMAP/SMTP failures are logged only as generic exceptions.

## 8. Phase-by-Phase Implementation Plan

### Phase 0 — Discovery & Validation

- Objective:
  - Freeze the current Gmail shared inbox contract and confirm product scope decisions that are not evidenced in code.
- Scope:
  - Inventory all Gmail-dependent routes, UI props, models, jobs, tests, and migrations.
  - Confirm whether multi-mailbox per company and mailbox-level permissions are required.
- Affected code:
  - `app/Services/GmailService.php`
  - `app/Http/Controllers/GmailController.php`
  - `app/Http/Controllers/GmailWebhookController.php`
  - `app/Http/Controllers/ConversationController.php`
  - `resources/js/pages/conversations/index.tsx`
  - `resources/js/pages/settings/components/integrations-settings.tsx`
  - current mail-domain migrations.
- Schema impact:
  - None yet.
- Dependencies:
  - Product confirmation on mailbox count, drafts/forwards, visibility rules.
- Risks:
  - Building generic abstractions that do not match required product behavior.
- Deliverables:
  - Dependency map and migration strategy decision.
- Exit criteria:
  - All Gmail-specific touchpoints enumerated and non-code business decisions resolved.

### Phase 1 — Provider Abstraction Layer

- Objective:
  - Introduce provider-neutral service boundaries while keeping Gmail working as the first provider implementation.
- Scope:
  - Add mailbox provider contracts and adapt Gmail behind them.
  - Remove direct controller/job construction of `GmailService`.
- Affected code:
  - `app/Services/GmailService.php`
  - `app/Http/Controllers/ConversationController.php`
  - `app/Http/Controllers/GmailController.php`
  - `app/Jobs/SyncGmailThreadsJob.php`
  - `app/Console/Commands/RenewGmailWatch.php`
- Schema impact:
  - Additive mailbox/account abstraction schema or transitional mapping columns.
- Dependencies:
  - Phase 0 migration strategy.
- Risks:
  - Breaking Gmail behavior during indirection refactor.
- Deliverables:
  - Provider-neutral service interfaces with Gmail adapter implementation.
- Exit criteria:
  - Existing Gmail tests and inbox flows work through the abstraction.

### Phase 2 — IMAP Ingestion Engine

- Objective:
  - Add provider-agnostic inbound sync based on IMAP for non-Gmail accounts.
- Scope:
  - Mailbox connection model for IMAP credentials.
  - Polling sync job(s).
  - Message normalization, dedupe, and thread persistence.
- Affected code:
  - New mailbox sync services/jobs.
  - Conversation retrieval paths that assume Gmail-only account model.
- Schema impact:
  - Mailbox credential/state storage.
  - Provider-neutral external message/thread ID fields.
  - IMAP sync cursor/state persistence.
- Dependencies:
  - Provider abstraction from Phase 1.
- Risks:
  - Duplicate imports, folder/flag mapping ambiguity, incremental sync complexity.
- Deliverables:
  - Working IMAP account connection and inbound sync into existing thread/message UI.
- Exit criteria:
  - Non-Gmail mailbox can sync inbound mail into conversations without Gmail tables being the primary dependency.

### Phase 3 — SMTP Sending Pipeline

- Objective:
  - Replace Gmail API dependence for conversation sending on non-Gmail accounts with mailbox-scoped SMTP sending.
- Scope:
  - SMTP connection model.
  - Compose/reply sender implementation.
  - Delivery result persistence and failure handling.
- Affected code:
  - `ConversationController::compose()`
  - `ConversationController::reply()`
  - `GmailService::sendMessage()` callers
  - follow-up sender path in `ProcessFollowUps`.
- Schema impact:
  - Provider-neutral outbound state fields on messages if needed.
- Dependencies:
  - Provider abstraction and mailbox account model.
- Risks:
  - Reply threading consistency across SMTP-sent messages and IMAP-ingested replies.
- Deliverables:
  - SMTP send path for compose/reply/follow-up on non-Gmail mailboxes.
- Exit criteria:
  - A connected non-Gmail mailbox can send outbound mail and the result appears correctly in shared inbox history.

### Phase 4 — Shared Inbox Core

- Objective:
  - Make the conversation model mailbox/provider-neutral at the business layer.
- Scope:
  - Remove Gmail assumptions from conversation listing, filtering, archive behavior, attachments, tracking, and frontend types.
  - Preserve shared visibility, assignment, CRM linkage, and follow-up workflows.
- Affected code:
  - `app/Models/EmailThread.php`
  - `app/Models/EmailMessage.php`
  - `app/Http/Controllers/ConversationController.php`
  - `resources/js/types/conversations.ts`
  - `resources/js/pages/conversations/index.tsx`
- Schema impact:
  - Provider-neutral mailbox/thread/message references.
  - Optional provider-neutral tag/folder model if product requires it.
- Dependencies:
  - Phases 1-3.
- Risks:
  - UI/API contract churn and migration/backfill errors.
- Deliverables:
  - Provider-neutral shared inbox flows for list, read, assign, reply, archive/status, attachments.
- Exit criteria:
  - Conversation UI no longer depends on Gmail-specific object names or behavior for its core flow.

### Phase 5 — Decoupling Gmail Dependencies

- Objective:
  - Demote Gmail-specific behavior from core architecture to provider plugin/adapter status.
- Scope:
  - Rename or supersede Gmail-specific schema.
  - Convert Gmail webhook and watch renewal to optional provider-specific optimization.
  - Generalize activity logging and tracking IDs.
- Affected code:
  - Gmail migrations/models/controllers/jobs/events/routes.
- Schema impact:
  - Backfill provider-neutral columns/tables.
  - Retire or alias Gmail-specific columns after cutover.
- Dependencies:
  - Shared inbox core complete and backfilled.
- Risks:
  - Migration risk if old UI/jobs still reference Gmail-specific fields.
- Deliverables:
  - Gmail becomes one provider implementation, not the shared inbox core.
- Exit criteria:
  - Core code paths run without requiring `GmailAccount`, `gmail_thread_id`, or `gmail_message_id` as primary dependencies.

### Phase 6 — Reliability & Scaling

- Objective:
  - Harden sync/send operations for production.
- Scope:
  - Idempotency keys and dedupe.
  - Failure classification and retry policy by provider.
  - Sync scheduling and backoff.
  - Mailbox health status and reconnect workflow.
- Affected code:
  - queue jobs, activity logging, provider services, settings UI.
- Schema impact:
  - Additional operational status/error fields or event tables as justified.
- Dependencies:
  - Multi-provider flows working end-to-end.
- Risks:
  - Performance degradation on large mailboxes without batching/index tuning.
- Deliverables:
  - Operational reliability framework for sync/send failures.
- Exit criteria:
  - System handles retryable failures, duplicate events, and reconnect scenarios predictably.

### Phase 7 — Testing & Rollout

- Objective:
  - Validate regression safety and cut over incrementally.
- Scope:
  - Expand tests beyond current Gmail OAuth/follow-up coverage.
  - Dual-run Gmail through abstraction before enabling IMAP/SMTP mailboxes.
  - Progressive rollout by company/mailbox.
- Affected code:
  - `tests/Feature/GmailOAuthTest.php`
  - conversation feature tests
  - new provider tests
  - migration/backfill scripts.
- Schema impact:
  - None beyond rollout markers if needed.
- Dependencies:
  - All previous phases complete.
- Risks:
  - Existing Gmail behavior regressing during provider-neutral rollout.
- Deliverables:
  - Automated test suite and rollout checklist.
- Exit criteria:
  - Gmail regression suite passes, IMAP+SMTP mailbox acceptance tests pass, and backfill validation is complete.

## 9. Risk Register

| Risk | Current Evidence | Impact |
|---|---|---|
| Duplication issues | Sync dedupe relies on Gmail snippet/thread fetch heuristics and Gmail IDs. | IMAP polling may create duplicates without stronger external ID rules. |
| Threading issues | Local threads depend on Gmail thread IDs plus limited header threading. | SMTP/IMAP cross-provider replies may split threads. |
| SMTP failures | No conversation SMTP pipeline exists today. | New outbound path may fail without delivery-state modeling and retries. |
| Credential risks | IMAP/SMTP secret storage model not present; current settings store many secrets in DB. | Unsafe or inconsistent mailbox credential handling. |
| Race conditions | Webhook-triggered Gmail sync plus manual sync plus async reply sync already coexist. | Multi-provider polling/webhook mix can double-process messages. |
| Permission leaks | Access is company-wide via `created_by`; mailbox-level visibility is not modeled. | Shared inbox for multiple mailboxes may expose wrong conversations. |
| Migration risks | Core tables and follow-up/tracking tables embed Gmail identifiers. | Backfill/cutover complexity is high. |
| Performance issues | Conversation listing filters on JSON labels and participants; attachment metadata is fetched live from Gmail. | Larger non-Gmail mailboxes may degrade list/detail performance. |

## 10. Open Questions / Blockers

- No evidence was found for IMAP requirements beyond the user brief, so mailbox onboarding UX, credential types, and auth mechanisms for non-Gmail providers are product-level blockers.
- No evidence was found for drafts/forwards in the current system; if required, they need explicit scope confirmation before schema/API design.
- No evidence was found for mailbox-level visibility rules; if required, current company-wide authorization is insufficient and needs a new membership model.
- `email_click_logs` exists but no click-tracking implementation was found; if click-based follow-up triggers are expected to work, current capability is incomplete.

## 11. Final Recommendation

Readiness to proceed:
- Proceed only after Phase 0 validation. The repo is sufficiently complete to plan the work, but the implementation should not start as a direct IMAP/SMTP add-on to current Gmail services.

Required prerequisites:
- Confirm product scope for:
  - one vs multiple mailboxes per company
  - drafts/forwards
  - mailbox-level visibility rules
  - attachment storage policy
- Approve an additive migration strategy that preserves Gmail production behavior while provider-neutral columns/tables are introduced.

Execution order:
1. Introduce provider abstraction with Gmail as adapter.
2. Add provider-neutral mailbox/account schema.
3. Build IMAP ingestion.
4. Build mailbox-scoped SMTP sending.
5. Refactor shared inbox APIs/UI onto provider-neutral contracts.
6. Backfill and retire Gmail-core assumptions.
7. Harden reliability and roll out incrementally.

MVP definition:
- One Gmail mailbox and one non-Gmail IMAP+SMTP mailbox can both:
  - connect
  - sync inbound messages
  - display in shared conversations
  - support shared visibility and assignment
  - send compose/reply
  - preserve basic threading

Production readiness criteria:
- Gmail regression suite passes through the abstraction layer.
- Non-Gmail IMAP+SMTP mailbox passes end-to-end sync/send/threading tests.
- Duplicate message handling is verified.
- Mailbox credentials are stored securely.
- Retry/reconnect flows are implemented for both inbound and outbound failures.
- Migration/backfill validation proves no loss of existing Gmail conversation history.
