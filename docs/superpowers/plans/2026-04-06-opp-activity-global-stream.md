# Opp Activity ↔ Global Opportunity Stream — Implementation Plan

> **For agentic workers:** Implement task-by-task; verify before completion claims.

**Goal:** Replace hardcoded “Opp Activity” rows in the Conversations Hub opportunity expand section with the same logical stream as the Opportunity Detail page (latest 3 items), aligned with Lead `recent_stream_preview` patterns.

**Architecture:** `OpportunityActivityStreamService` builds a time-ordered stream from `OpportunityActivity` (canonical, matches `OpportunityController@show`) plus standalone `OpportunityComment` rows that are not already represented by a comment activity (dedupe by user + description + nearby timestamp). `ConversationController` sets `recent_stream_preview` on each opportunity attached to thread leads after existing attach methods. Frontend maps `opp.recent_stream_preview` with shared dot styling; extend `field_changed` handling for `opportunity_stage_id`.

**Tech Stack:** Laravel, React/Inertia, existing `Opportunity` / `OpportunityActivity` / `OpportunityComment`.

---

### Task 1: `OpportunityActivityStreamService`

- Create `app/Services/OpportunityActivityStreamService.php` with explicit `use App\Models\Opportunity;` and `use App\Models\OpportunityActivity;` (avoid `App\Services\Opportunity` resolution bug).
- `streamItemsCollection(Opportunity $opportunity): Collection` — activities `orderBy(created_at, desc)`, merge deduped synthetic items from comments, `sortByDesc(created_at)`.
- `previewItems(Opportunity $opportunity, int $limit = 3): array` — take 3, serialize.
- `serializeItem(OpportunityActivity|object): array` — same shape as lead preview (`id`, `activity_type`, `title`, `description`, `created_at`, `field_changed`, `user_id`, `user`).

### Task 2: `ConversationController`

- Add `attachOpportunityRecentStreamPreview(EmailThread $thread)` looping `lead->opportunities`, `setAttribute('recent_stream_preview', ...)`.
- Call after `attachLeadRecentStreamPreview` in `show`, `update`, `assign`.

### Task 3: `resources/js/pages/conversations/index.tsx`

- Extend `getLeadStreamPreviewDotClass` to treat `field_changed === 'opportunity_stage_id'` like lead status (violet).
- Replace hardcoded Opp Activity block with `opp.recent_stream_preview` map (reuse lead section patterns: `sanitizeHtml`, `timeAgo`, empty state).

### Verification

- `php -l` on new/changed PHP files
- `npm run build` exit 0
