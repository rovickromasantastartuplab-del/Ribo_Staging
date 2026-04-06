# Conversation Hub Edge-to-Edge Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove outer “box-in-a-box” padding and cramped typography on the Conversations Hub while keeping the four-pane layout behavior identical (folder rail, thread/calendar pane, thread detail, CRM sidebar; calendar resize handle when applicable).

**Architecture:** Extend `PageTemplate` with an opt-in `noOuterPadding` flag that zeros the page-level `p-4 md:p-6 lg:p-8` wrapper while keeping a padded title row so the page heading does not collide with viewport edges. The Conversations page opts in, removes the inner rounded/shadow “card” wrapper around the hub, bumps primary text from `text-xs` / `text-[10px]` toward `text-sm` / `text-xs`, and increases selected internal vertical stacks from `space-y-2` to `space-y-4`. No changes to pane visibility rules, `flex`/`grid` structure, or calendar width state.

**Tech Stack:** React, Inertia, Tailwind, existing `PageTemplate` / `AppLayout`.

---

### Task 1: PageTemplate — outer bleed prop

**Files:**
- Modify: `resources/js/components/page-template.tsx`

- [ ] **Step 1:** Add optional `noOuterPadding?: boolean` (default `false`) to `PageTemplateProps`.

- [ ] **Step 2:** Import `cn` from `@/lib/utils`. Build the outer wrapper classes:
  - Default: keep `flex w-full max-w-full flex-1 flex-col gap-4 p-4 md:p-6 lg:p-8 overflow-x-hidden`.
  - When `noOuterPadding`: use `p-0 gap-0 overflow-x-hidden` on the outer wrapper; add `px-4 md:px-6 lg:px-8 pt-4 pb-2` to the existing title/actions row only so the h1 remains readable.

- [ ] **Step 3:** Merge `className` with `cn(..., className)` so callers can still override without dropping `overflow-x-hidden`.

- [ ] **Step 4:** Run `npx eslint resources/js/components/page-template.tsx` (or project lint) and fix any issues.

---

### Task 2: Conversations index — enable bleed and flatten chrome

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx`

- [ ] **Step 1:** On `<PageTemplate>`, set `noOuterPadding` in addition to existing `noPadding`.

- [ ] **Step 2:** Replace the main hub wrapper (currently `border rounded-xl ... shadow-sm`) with a flat full-bleed container: keep `flex flex-col`, `min-h-0`, `overflow-hidden`, and height utilities; drop outer `rounded-xl` and `shadow-sm` (retain subtle borders only where panes already divide, e.g. `border-b` / pane `border-r`).

- [ ] **Step 3:** Update the comment block that estimates vertical overhead (~200px) if padding removal changes the effective viewport — adjust `lg:h-[calc(100vh-…)]` so the hub still fills the main column without double scrollbars.

---

### Task 3: Typography — thread list and messages

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx`

- [ ] **Step 1:** Thread list rows: participant name, subject, snippet → `text-sm` where they were `text-xs`; timestamps `text-[10px]` → `text-xs`; avatar fallback `text-[10px]` → `text-xs`.

- [ ] **Step 2:** Thread detail header (`h2` / participants line): step up from `text-xs` secondary to `text-sm` where it is primary reading content.

- [ ] **Step 3:** Message bubbles: set body container to `text-sm` (remove `text-xs` on small breakpoints if present); sender line `text-xs` → `text-sm`; meta (`time`, “sent by”) `text-[10px]` → `text-xs`.

- [ ] **Step 4:** Search input in thread list: `text-xs` → `text-sm` for the input text class if present.

---

### Task 4: Internal spacing — space-y-2 → space-y-4

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx`

- [ ] **Step 1:** Replace `space-y-2` with `space-y-4` on lead detail rows block and expanded opportunity detail block (and any other hub-internal stacks explicitly called out as `space-y-2` for primary pane content).

---

### Task 5: Verification

- [ ] **Step 1:** Run the project frontend check (e.g. `npm run build` or `npm run lint` from repo root per `package.json`).

- [ ] **Step 2:** Manually smoke-check: open `/conversations`, confirm no horizontal page gutter; select thread, open CRM sidebar, switch calendar folder — layout matches prior breakpoints.

---

## Self-review

- **Spec coverage:** Outer padding removal, typography, internal spacing, 4-pane preservation — covered.
- **Placeholder scan:** None.
- **Risk:** `noOuterPadding` is opt-in; other pages unchanged.
