# Open Loop AI Task Extraction Design

Date: 2026-04-14
Owner: Codex + Rovick
Status: Drafted for implementation approval

## Objective

Implement missing extraction and persistence of open loops into `ai_tasks` so the Relationship History Task Checklist is populated and stays accurate.

This design covers:
- open loop extraction from both incoming and outgoing thread messages
- task creation and deduplication (reuse existing tasks)
- automatic reopen behavior for previously completed tasks when commitments become active again
- hybrid execution path for freshness and reliability

## Scope

In scope:
- Add backend extraction + upsert/reopen pipeline for AI tasks
- Integrate with sync/event path and memory read path
- Add targeted automated tests for create/reuse/reopen/reconcile behavior

Out of scope:
- UI redesign of Task Checklist card
- replacing current manual task toggle endpoint behavior
- broad historical backfill across all old conversations (unless explicitly triggered later)

## Recommended Architecture

Use a hybrid model:

1. Sync/event path as primary writer
- During Gmail thread/message sync, evaluate new content for open-loop candidates.
- Upsert into `ai_tasks` immediately for near real-time checklist freshness.

2. Memory API path as idempotent reconciler
- During `ConversationAiMemoryService::show(...)`, run reconciliation before returning tasks.
- If sync misses an event, memory load self-heals missing or stale tasks.

Rationale:
- Sync path gives fast visible updates.
- Memory path provides reliability and drift correction.

## Components

### `OpenLoopExtractor` (new service/helper)

Responsibility:
- Convert relevant thread message text into normalized open-loop candidates.
- Support both inbound and outbound messages.

Output contract for each candidate:
- `title` (display text)
- `loop_key` (normalized fingerprint)
- `confidence` (`strong` | `weak`)
- `thread_id`
- `message_id` or source marker
- `detected_at`

### `OpenLoopTaskService` (new service)

Responsibility:
- Idempotent persistence logic for `AiTask`.
- Dedup/reuse/reopen decisions.

Main operations:
- `upsertFromThread(...)` for sync/event path
- `reconcileEntity(...)` for memory path

## Matching and Dedup Rules

Task match key:
- same `created_by`
- same entity (`contact_id` or `lead_id`)
- same `loop_key`

`loop_key` normalization:
- lowercase
- trim leading/trailing whitespace
- collapse repeated internal spaces
- remove trailing punctuation noise
- optional removal of common polite prefixes (e.g. "please", "kindly")

Behavior:
- No match: create new `AiTask` (`source='ai'`, `is_completed=false`)
- Match exists and open: refresh metadata only, no duplicate creation
- Match exists and completed:
  - if `confidence=strong`, reopen task
  - if `confidence=weak`, do not reopen automatically

## Reopen Policy (Best Behavior)

When reopening:
- set `is_completed=false`
- set `completed_at=null`
- increment `metadata_json.reopened_count`
- update `metadata_json.last_reopened_at`

Strong evidence guideline:
- clear actionable commitment signal in recent message(s)
- recurring intent aligns with existing `loop_key`

Weak evidence guideline:
- ambiguous phrasing or no clear action ownership
- do not flip completed tasks back to open

## Data Model Usage

No required schema migration for initial implementation.

Use `ai_tasks.metadata_json` to track loop lifecycle:
- `loop_key`
- `evidence_hash`
- `evidence_thread_id`
- `last_detected_at`
- `last_reopened_at`
- `reopened_count`

## Integration Points

### Sync/Event integration (primary)
- Hook after message persistence in Gmail sync flow.
- Build open-loop candidates from latest message batch.
- Call `OpenLoopTaskService::upsertFromThread(...)`.

### Memory integration (safety net)
- In `ConversationAiMemoryService::show(...)`, before task query:
  - call `OpenLoopTaskService::reconcileEntity(...)`
- Continue returning tasks through existing `ConversationAiMemoryController` response contract.

### Existing task toggle endpoint
- Keep `PATCH /ai/tasks/{task}` behavior unchanged.
- Manual user completion remains source of truth for explicit close action.

## Error Handling

Sync/event path:
- open-loop extraction/persistence failures should not break full sync job
- log structured warning with entity/thread identifiers

Memory path:
- reconciliation failures should fail soft
- continue returning existing tasks where possible

## Testing Strategy

Add/extend feature/service tests to cover:

1. creates task for unseen open loop
2. reuses existing open task (no duplicate)
3. reopens completed task on strong repeated signal
4. does not reopen completed task on weak signal
5. sync/event path writes tasks for inbound + outbound messages
6. memory path reconciliation heals missing task
7. cross-company isolation preserved

## Rollout Notes

Phase 1:
- enable extraction + upsert with conservative strong-signal reopen logic

Phase 2 (optional):
- improve extraction quality and confidence calibration
- optional bulk backfill job if business needs historical coverage

## Risks and Mitigations

Risk: duplicate noisy tasks from message wording variation  
Mitigation: robust `loop_key` normalization and metadata evidence hashing

Risk: completed tasks reopening too aggressively  
Mitigation: reopen only on strong evidence, preserve audit metadata

Risk: drift between sync and memory paths  
Mitigation: idempotent upsert logic shared by both paths

## Implementation Readiness

Approved decisions captured:
- extract from both inbound + outbound messages
- hybrid model (sync writer + memory reconciler)
- reuse existing tasks (dedup)
- auto reopen completed tasks only with strong new evidence

Ready to move to implementation planning and code changes.
