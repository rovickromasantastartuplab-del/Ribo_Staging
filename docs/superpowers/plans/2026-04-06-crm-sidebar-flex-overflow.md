# CRM Sidebar Flex Overflow Hardening

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop long contact names, lead names, company lines, and opportunity titles from overflowing the CRM Context Sidebar (Pane 4) in `resources/js/pages/conversations/index.tsx` by enforcing `min-w-0` on flex children and `truncate` / controlled overflow on text.

**Architecture:** Tailwind flexbox fix only: no API or state changes. The absolute sidebar column keeps `md:w-[300px]`; inner rows use `min-w-0 flex-1 overflow-hidden` on text clusters and `shrink-0` on icons/badges/controls so truncation boundaries resolve correctly.

**Tech Stack:** React, Tailwind CSS.

---

### Task 1: Pane shell and contact header

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx` (Pane 4 block, ~lines 1810–1838)

- [ ] **Step 1:** On the absolute sidebar root `div`, add `min-w-0` so nested flex descendants can shrink within the fixed width.

- [ ] **Step 2:** On the header row `flex items-center justify-between`, add `gap-2 min-w-0`.

- [ ] **Step 3:** On the wrapper around avatar + text (`flex items-center gap-2`), add `min-w-0 flex-1 overflow-hidden` so the close button always wins horizontal space.

- [ ] **Step 4:** Keep inner text `div` as `min-w-0 flex-1` with `truncate` on both lines (contact name and subtitle).

---

### Task 2: Lead card header row

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx` (~1880–1888)

- [ ] **Step 1:** On the lead header row, add `gap-2 min-w-0`.

- [ ] **Step 2:** On the left cluster, use `flex min-w-0 flex-1 items-center gap-2 overflow-hidden`.

- [ ] **Step 3:** On `{lead.name}` span, add `min-w-0 flex-1 truncate` (in addition to existing `text-sm font-semibold`).

---

### Task 3: Opportunity card header row

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx` (~2095–2133)

- [ ] **Step 1:** On the opportunity header row `flex items-center justify-between`, add `gap-2 min-w-0`.

- [ ] **Step 2:** On the name `button`, ensure `min-w-0 flex-1 overflow-hidden` and name `span` has `min-w-0 flex-1 truncate`.

- [ ] **Step 3:** Keep stage controls in `shrink-0` cluster; add `min-w-0` on the outer row if needed so the button cannot push past the card edge.

---

### Task 4: Lead detail value rows (optional hardening)

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx` (Company / Owner rows in lead card)

- [ ] **Step 1:** For each `flex justify-between` row, add `min-w-0` to the row and `min-w-0 flex-1 truncate text-right` (or `text-left` as appropriate) on the value side so long company names do not overflow.

---

### Task 5: Verification

- [ ] **Step 1:** Run `npm run build` from repo root; expect exit 0.

- [ ] **Step 2:** Manual: open Conversations, open CRM sidebar with a long lead name / long opp name; confirm ellipsis and no border overlap.

---

## Self-review

- Covers Contact header, Lead header, Opportunity header, and value rows that share the same failure mode.
- No placeholder steps.
