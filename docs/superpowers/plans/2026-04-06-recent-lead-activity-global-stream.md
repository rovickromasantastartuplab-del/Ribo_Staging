# Recent Lead Activity ↔ Global Stream — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace thread-snippet–based “Recent Lead Activity” in the Conversations Hub CRM sidebar with the same merged global lead activity stream used on the Lead Detail page (latest 3 items).

**Architecture:** Extract `LeadController`’s `getStreamItemsCollection` logic into `App\Services\LeadActivityStreamService` with `streamItemsCollection(Lead)` and `previewItems(Lead, int $limit = 3)` returning JSON-safe arrays. `ConversationController` attaches `recent_stream_preview` on each linked lead after loading opportunities (`show`, `update`, `assign`). Frontend maps those items with icon/dot styling aligned to `leads/show.tsx` stream helpers (`activity_type` + `field_changed` for status).

**Tech Stack:** Laravel, Inertia/React, existing `Lead` / `LeadEvent` / email thread relations.

---

### Task 1: `LeadActivityStreamService`

**Files:**
- Create: `app/Services/LeadActivityStreamService.php`
- Modify: `app/Http/Controllers/LeadController.php` (replace private `getStreamItemsCollection` body with service calls)

- [x] **Step 1:** Add `LeadActivityStreamService` with:
  - `streamItemsCollection(Lead $lead): Collection` — move logic from `LeadController::getStreamItemsCollection` (activities + LeadEvent + email thread messages, sorted by `created_at` desc).
  - `serializeItem(mixed $item): array` — normalize `LeadActivity` models and stdClass stream objects to arrays with `id`, `activity_type`, `title`, `description`, `created_at` (ISO8601), `field_changed`, `user_id`, `user` (`id`, `name`, `avatar`), `is_lead_event`, `metadata`, `channel` where present.
  - `previewItems(Lead $lead, int $limit = 3): array` — `streamItemsCollection` → take `$limit` → map `serializeItem` → `values` → `all()`.

- [x] **Step 2:** In `LeadController`, remove private `getStreamItemsCollection`; use `app(LeadActivityStreamService::class)->streamItemsCollection($lead)` in `show()` and `apiActivities()`.

- [x] **Step 3:** Run `php -l app/Services/LeadActivityStreamService.php` and `php -l app/Http/Controllers/LeadController.php`

- [ ] **Step 4:** Commit: `refactor(leads): extract global activity stream to LeadActivityStreamService`

---

### Task 2: Attach preview on conversation thread JSON

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`

- [x] **Step 1:** Add `use App\Services\LeadActivityStreamService;`

- [x] **Step 2:** Add protected method `attachLeadRecentStreamPreview(EmailThread $thread): void` that loops `$thread->leads` and sets each lead’s `recent_stream_preview` attribute to `app(LeadActivityStreamService::class)->previewItems($lead, 3)`.

- [x] **Step 3:** After `$this->attachLeadOpportunities($thread);` in `show`, `update`, and `assign`, call `$this->attachLeadRecentStreamPreview($thread);`

- [x] **Step 4:** Run `php -l app/Http/Controllers/ConversationController.php`

- [ ] **Step 5:** Commit: `feat(conversations): include lead global activity preview on thread JSON`

---

### Task 3: Sidebar UI — map real stream items

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx`

- [x] **Step 1:** Import icons used on Lead show stream mapping: `MessageCircle`, `Target` (add to lucide import list alongside existing). *(Implemented with dot colors only; no extra lucide imports.)*

- [x] **Step 2:** Add helper `getLeadStreamPreviewDotClass(activity: { activity_type?: string; field_changed?: string })` mirroring `leads/show.tsx` `getActivityColor` intent:
  - `created` → emerald ring/dot
  - `updated` → blue (if `field_changed === 'lead_status_id'`, optional violet accent — optional; default blue per lead page badge “Updated”)
  - `assigned` → purple
  - `converted` → orange
  - `comment` → indigo
  - `email` → sky
  - `message` → emerald/teal (omnichannel)
  - default → muted gray

- [x] **Step 3:** Replace the two hardcoded timeline rows under “Recent Lead Activity” with:
  - `const preview = selectedThread.leads?.[0]?.recent_stream_preview ?? [];`
  - If `preview.length === 0`, show a short empty state (`t('No activities yet')` or reuse existing empty phrasing).
  - Else `preview.map` each activity: vertical line + dot with `getLeadStreamPreviewDotClass`, title = `activity.title` (plain text), secondary = `activity.description` — for HTML descriptions use `sanitizeHtml` + `dangerouslySetInnerHTML` in a `line-clamp-2` div (same patterns as lead page for status HTML), or strip tags for minimal preview.
  - Timestamp: `timeAgo(activity.created_at)` using existing helper.

- [x] **Step 4:** Keep “Open Full History Stream” link to `route('leads.show', selectedThread.leads[0].id)` when leads exist.

- [x] **Step 5:** If `mergeThreadIntoState` or similar copies thread from API, ensure `recent_stream_preview` is preserved (no stripping).

- [x] **Step 6:** Run frontend build: `npm run build` (from project root).

- [ ] **Step 7:** Commit: `feat(conversations): render Recent Lead Activity from global stream preview`

---

### Verification (required before completion)

- `php -l` on changed PHP files
- `npm run build` — exit 0
- Manual: open a thread with a linked lead that has CRM + email/omnichannel history; sidebar shows up to 3 items consistent with Lead page ordering; link opens full stream.

---

## Self-review

- Spec coverage: merged stream (not raw `activities` only) — Task 1–2; UI types/icons — Task 3; parity with Lead page — serialize + dot mapping.
- No placeholders in executable steps above beyond optional violet accent note.
