# CRM Sidebar Backend Connect — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist lead status and opportunity stage changes from the Conversation Hub CRM sidebar via existing Laravel endpoints, and load real opportunities for the active thread.

**Architecture:** Use `PUT leads/{lead}/update-status` with `{ lead_status_id }` and `PUT opportunities/{opportunity}/update-status` with `{ opportunity_stage_id }`. Map display names to IDs using `leadStatuses` and `opportunityStages` from Inertia shared props. Attach opportunities to each linked lead (matched by account email = lead email) when loading a thread. Optimistic UI with rollback on error; merge server JSON into `selectedThread` without full page reload.

**Tech Stack:** Laravel, Inertia props, React + axios, existing `LeadController::updateStatus` / `OpportunityController::updateStatus`.

---

## Scope & safety (brainstorming summary)

- **Permissions:** Lead dropdown uses `edit-leads`; opportunity dropdown uses `edit-opportunities` (owners bypass via existing `isOwner` pattern). `manage-conversations` alone is not sufficient for CRM field edits.
- **No flicker:** Update local state optimistically, then replace with API response; clear optimistic overrides on success; rollback on failure.
- **Opportunity source:** Opportunities are loaded for leads whose email matches an account’s email (same company). Mock demo rows were removed.

---

## Tasks

- [x] **Task 1 — Backend:** Add `opportunityStages` to `ConversationController@index` (same pattern as `leadStatuses`). Add `attachLeadOpportunities()` and call it from `show`, `update`, and `assign` after loading `leads.leadStatus`.
- [x] **Task 2 — Frontend:** Read `opportunityStages` from `usePage`. Add `canEditLeadStatus` / `canEditOpportunityStage`, `savingLeadId` / `savingOppId`, `persistLeadStatus` / `persistOpportunityStage` with JSON headers and merge responses into `selectedThread`.
- [x] **Task 3 — UI:** Lead `Select` options = full `leadStatuses` list. Opportunity `Select` options = `opportunityStages`. Remove mock opportunities. Use `opportunity_stage` name for display. Probability from stage when available.

## Verification

- `php -l app/Http/Controllers/ConversationController.php`
- `npm run build` (or project frontend build) — ensure `resources/js/pages/conversations/index.tsx` compiles.
- Manual: open a thread with a linked lead; change lead status — persists after refresh. With an account email matching the lead, opportunities appear; change stage — persists. User without `edit-leads` / `edit-opportunities` sees read-only labels.
