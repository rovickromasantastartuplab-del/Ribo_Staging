# Remove Conversation History Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the Conversation Hub `History` tab and all tab-specific backend/frontend code paths while keeping regular conversation workflows intact.

**Architecture:** Delete the `history` folder option and all `selectedFolder === 'history'` branches from the conversation page, then remove now-unused history endpoints and controller methods. Keep generic `activities` endpoint and lead-side activity stream behavior unchanged.

**Tech Stack:** Laravel (PHP), Inertia + React/TypeScript, route helper-based API calls.

---

### Task 1: Remove History tab UI and frontend history state

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx`
- Verify: `resources/js/pages/conversations/index.tsx`

- [ ] **Step 1: Remove `History` from folder selectors and icon imports**

```tsx
// remove this import
// History as HistoryIcon,

// remove these folder entries
// { key: 'history', icon: HistoryIcon, label: t('History'), count: 0 },
```

- [ ] **Step 2: Remove history-only React state and loaders**

```tsx
// remove:
// historyActivities, loadingHistory, loadingParticipants, historyParticipants
// historyPage, hasMoreHistory, participantsPage, hasMoreParticipants
// isSyncingHistory, selectedParticipant, searchParticipants
```

- [ ] **Step 3: Remove history fetch/sync helpers and observers**

```tsx
// delete functions/useEffects that are only used by History tab:
// fetchHistoryParticipants()
// fetchParticipantActivities()
// handleSeamlessSync()
// participantObserverTarget observer
```

- [ ] **Step 4: Replace conditional History view with the existing non-history view**

```tsx
// remove:
// {selectedFolder === 'history' ? (...) : (...)}

// keep only the existing conversation list/detail rendering block
```

- [ ] **Step 5: Run targeted checks**

Run: `npm run build` (or project-equivalent frontend check)  
Expected: no TypeScript/compile errors from removed history symbols.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/conversations/index.tsx
git commit -m "refactor: remove conversation history tab UI and logic"
```

### Task 2: Remove History tab backend endpoints

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ConversationController.php`

- [ ] **Step 1: Remove history-specific routes**

```php
// delete:
// Route::get('api/conversations/history/participants', ...)->name('api.conversations.history.participants');
// Route::post('api/conversations/history/sync', ...)->name('api.conversations.history.sync');
```

- [ ] **Step 2: Remove history-specific controller methods**

```php
// delete methods:
// public function syncContactHistory(Request $request)
// public function historyParticipants(Request $request)
```

- [ ] **Step 3: Keep other conversation endpoints unchanged**

```php
// keep:
// activities(), threads(), show(), syncInboxHistory(), reply(), compose(), etc.
```

- [ ] **Step 4: Run backend checks**

Run: `php artisan route:list | rg "conversations/history|api.conversations.history"`  
Expected: no matches.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/ConversationController.php
git commit -m "refactor: remove conversation history API endpoints"
```

### Task 3: Clean up dead copy and ensure no references remain

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx` (if any remaining history-only labels)
- Verify: repository-wide

- [ ] **Step 1: Remove or update history-tab copy strings in Conversation Hub**

```tsx
// remove text labels tied only to removed tab:
// "Communication History", "No contacts with history", "Browse activities by contact", etc.
```

- [ ] **Step 2: Repo-wide reference scan**

Run: `rg "api\\.conversations\\.history|selectedFolder === 'history'|fetchHistoryParticipants|syncContactHistory|historyParticipants" resources app routes tests docs`  
Expected: no matches in source files.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/conversations/index.tsx
git commit -m "chore: remove remaining conversation history references"
```

### Task 4: Verification and regression checks

**Files:**
- Verify only

- [ ] **Step 1: Run lint/build checks used by this repo**

Run: `npm run build` and `php artisan test` (or the project’s standard targeted suite)  
Expected: pass, or failures unrelated to these changes are documented.

- [ ] **Step 2: Manual behavior verification**

Run manual checks in `/conversations`:
1. Folder list no longer shows `History`
2. Inbox/Sent/Archive/Calendar views still load
3. Thread open, reply compose/send UI remains functional
4. No console errors from removed history code

- [ ] **Step 3: Final status check**

Run: `git status`  
Expected: clean tree (if commits were made) or only intended files modified.
