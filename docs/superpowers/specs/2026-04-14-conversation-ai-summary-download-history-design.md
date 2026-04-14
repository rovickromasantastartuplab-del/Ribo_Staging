# Conversation AI Summary Download History Design

Date: 2026-04-14
Owner: Conversations / AI
Status: Proposed

## 1. Goal

Track every successful AI summary report download in a reusable version history so users can:
- See a modal history for a conversation thread.
- Re-download a previously saved version.
- Understand who last downloaded and how often.

This design applies to Conversation AI summary reports generated from `/ai/reports`.

## 2. Decisions Locked

- One saved version row per `ai_report_job_id`.
- Re-downloads of the same version increment `download_count` (no duplicate version row).
- History is visible to all users in the same company for that conversation thread.
- Hybrid persistence:
  - Save structured snapshot data (`snapshot_json`) for durability.
  - Save generated PDF file for exact re-download output.

## 3. Scope

In scope:
- Backend persistence for download versions.
- Modal API for report history by thread.
- Re-download endpoint by version id.
- Counter/audit fields for repeated downloads.
- UI modal in conversation AI area.
- Feature tests and UI tests for core flow.

Out of scope:
- Advanced analytics dashboards.
- External notification workflows.
- Retention policies and archival jobs (future iteration).

## 4. Data Model

Create table: `ai_report_versions`

Fields:
- `id` (bigint)
- `created_by` (company id)
- `email_thread_id` (thread id)
- `ai_report_job_id` (unique, foreign key-like association)
- `scope` (string)
- `snapshot_json` (json, normalized payload used for regeneration fallback)
- `pdf_path` (string, nullable)
- `template_version` (string, nullable)
- `download_count` (unsigned int, default 0)
- `first_downloaded_at` (timestamp, nullable)
- `last_downloaded_at` (timestamp, nullable)
- `last_downloaded_by` (user id, nullable)
- `created_at`, `updated_at`

Indexes:
- unique index on `ai_report_job_id`
- index on (`created_by`, `email_thread_id`)
- index on `last_downloaded_at`

Storage path convention:
- `storage/app/private/ai-reports/{companyId}/{jobId}.pdf`

## 5. API Design

### 5.1 Existing Download Endpoint (Enhanced)

`GET /ai/reports/{job}/download`

Behavior:
- Authorize same company.
- Resolve or create `ai_report_versions` row for job.
- On first successful download:
  - Render PDF.
  - Save file to private path.
  - Persist version row with `download_count = 1`, timestamps, downloader.
- On subsequent downloads:
  - Prefer saved file at `pdf_path`.
  - Increment `download_count` and update `last_downloaded_*`.
- Return downloaded PDF response.

### 5.2 New History Endpoint

`GET /ai/reports/history/{thread}`

Behavior:
- Authorize thread within company.
- Return list of versions for thread (newest `last_downloaded_at` first).
- Include downloader display info for modal.

Response shape (example):
- `id`
- `ai_report_job_id`
- `scope`
- `download_count`
- `first_downloaded_at`
- `last_downloaded_at`
- `last_downloaded_by` (id + name)
- `created_at`

### 5.3 New Re-download by Version Endpoint

`GET /ai/reports/version/{version}/download`

Behavior:
- Authorize company ownership.
- Serve stored PDF if present.
- If file missing and snapshot exists, regenerate and restore file.
- Increment `download_count` and update `last_downloaded_*` only on success.

## 6. UI / Modal Design

Add “Summary Download History” modal in conversation AI area.

Columns:
- Generated at
- Scope
- Download count
- Last downloaded at
- Last downloaded by
- Action: Download

Behavior:
- Open modal -> fetch history endpoint for current thread.
- Click Download -> call version download endpoint.
- Keep list concise and paginated if needed (simple limit in v1 is acceptable).

## 7. Error Handling

- Keep sanitized JSON error messages for failures.
- Do not increment counters when download fails.
- Missing stored file:
  - Attempt regeneration from `snapshot_json`.
  - If regeneration succeeds, replace file and continue.
  - If regeneration fails, return safe error.

## 8. Security and Authorization

- Enforce company boundary on all new endpoints.
- Never expose versions across company ids.
- Use same authorization style as current `ConversationAiReportController`.

## 9. Testing Plan

Feature tests:
- First download creates version row + stores file + sets `download_count = 1`.
- Second download of same job increments count without creating another row.
- History endpoint returns versions for current company thread only.
- Cross-company access is forbidden for history and version download.
- Missing file fallback regeneration path works.
- Failure path does not increment counters.

UI tests:
- Modal opens and renders fetched rows.
- Re-download action triggers expected endpoint and updates UI state.

## 10. Rollout Notes

- Backward compatible with existing `/ai/reports/{job}/download` callers.
- Existing reports remain downloadable; version rows are created lazily on first download after rollout.

## 11. Open Questions

None for v1. The current decisions are sufficient to proceed with implementation planning.
