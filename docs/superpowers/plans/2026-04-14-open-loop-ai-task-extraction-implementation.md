# Open Loop AI Task Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist open-loop commitments into `ai_tasks` from both inbound/outbound thread messages, with dedup/reuse and safe reopen behavior.

**Architecture:** Introduce a focused extractor + upsert service under `App\Services\AI`, invoke it in Gmail sync/send paths for freshness, and invoke reconciliation in memory read path for self-healing. Keep existing task toggle API unchanged.

**Tech Stack:** Laravel (PHP), Eloquent, Pest feature tests.

---

### Task 1: Add Open Loop Extraction + Upsert Services

**Files:**
- Create: `app/Services/AI/OpenLoopExtractor.php`
- Create: `app/Services/AI/OpenLoopTaskService.php`
- Test: `tests/Feature/AI/ConversationAiOpenLoopTaskServiceTest.php`

- [ ] **Step 1: Write failing service tests**
- [ ] **Step 2: Run targeted test file and confirm failure**
- [ ] **Step 3: Implement extractor + upsert/reopen logic**
- [ ] **Step 4: Run targeted test file and confirm pass**

### Task 2: Integrate Freshness Path in Gmail Sync/Send

**Files:**
- Modify: `app/Services/GmailService.php`
- Test: `tests/Feature/AI/ConversationAiOpenLoopTaskServiceTest.php`

- [ ] **Step 1: Add failing test for sync/send path invocation effect**
- [ ] **Step 2: Run targeted test and verify failure**
- [ ] **Step 3: Call open-loop upsert after thread sync and sent-message recording**
- [ ] **Step 4: Re-run targeted tests and verify pass**

### Task 3: Integrate Memory Reconciliation Safety Net

**Files:**
- Modify: `app/Services/AI/ConversationAiMemoryService.php`
- Modify: `tests/Feature/AI/ConversationAiMemoryApiTest.php`

- [ ] **Step 1: Add failing memory API test proving reconciliation creates/reuses tasks**
- [ ] **Step 2: Run targeted test and confirm failure**
- [ ] **Step 3: Inject and invoke reconcile path before memory task query**
- [ ] **Step 4: Re-run tests and confirm pass**

### Task 4: Verify and Regression Check

**Files:**
- Modify: `tests/Feature/AI/ConversationAiMemoryApiTest.php` (if needed for reopen edge case)

- [ ] **Step 1: Run focused AI memory/open-loop tests**
- [ ] **Step 2: Run existing memory API contract tests for regression**
- [ ] **Step 3: Fix any failures and keep changes minimal**
