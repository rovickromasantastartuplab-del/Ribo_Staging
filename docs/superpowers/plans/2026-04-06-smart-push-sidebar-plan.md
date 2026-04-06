# Smart Push CRM Sidebar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the Conversation Hub layout so the CRM context sidebar transitions from an absolute overlay on lg and smaller to a “Smart Push” xl+ flex sibling that nudges the email thread and smoothly animates as it appears/disappears.

**Architecture:** Pane 4 lives inside the main `md:flex-row` row; we will keep the overlay/backdrop markup and move it into that row, then switch its Tailwind classes between `absolute` (lg and below) and `relative flex-shrink-0` (xl+) while keeping the rest of the panes untouched.

**Tech Stack:** React + Inertia.js (`resources/js/pages/conversations/index.tsx`), TailwindCSS utility classes for layout and transitions.

---

### Task 1: Place the CRM sidebar fragment inside the main flex row container

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx:1010-1825`

- [ ] **Step 1: Relocate the existing `selectedThread && showContactSidebar` fragment so it lives at the end of the `<div className="flex flex-col md:flex-row flex-1 min-h-0 overflow-y-auto lg:overflow-hidden">` row instead of after it.**

```tsx
<div className="flex flex-col md:flex-row flex-1 min-h-0 overflow-y-auto lg:overflow-hidden">
    <FolderSidebar ... />

    <>
        {/* current Pane 2 + Pane 3 markup unchanged */}
    </>

    {selectedThread && showContactSidebar && (
        <>
            {/* existing backdrop + sidebar markup (unchanged for now) */}
        </>
    )}
</div>
```

- This keeps Pane 1–3 structure untouched and now allows the Pane 4 fragment to participate as a flex sibling when it switches from `absolute` to `relative` at `xl`.

### Task 2: Update the CRM sidebar/backdrop classes for the “Smart Push” behavior

**Files:**
- Modify: `resources/js/pages/conversations/index.tsx:1790-1850`

- [ ] **Step 1: Make the backdrop cover only lg and smaller, and allow it to fade with transitions.**

```tsx
<div
    className="absolute inset-0 z-20 bg-black/20 lg:bg-transparent lg:pointer-events-none xl:hidden transition-all duration-300 ease-in-out"
    onClick={() => setShowContactSidebar(false)}
/>
```

- This keeps the translucent backdrop for pop-up behavior, but hides it (`xl:hidden`) at larger widths where the sidebar is a flex sibling.

- [ ] **Step 2: Convert the sidebar panel container to the hybrid class set with fixed width, transitions, and xl flex-sibling traits.**

```tsx
<div
    className={cn(
        "absolute right-0 top-0 bottom-0 z-30 w-full min-w-0 md:w-[300px] max-w-[88vw] border-l flex flex-col bg-background shadow-2xl",
        "transition-all duration-300 ease-in-out",
        "xl:relative xl:z-10 xl:flex-shrink-0 xl:w-[300px] xl:max-w-none xl:border-l xl:shadow-none"
    )}
>
    {/* header + tabs + ScrollArea content as before */}
</div>
```

- The `absolute` positioning remains for small screens; at `xl` the `xl:relative`/`xl:flex-shrink-0` variant makes it a standard sibling inside the row, and the `transition-all duration-300 ease-in-out` keeps the push effect smooth while keeping the width locked at 300px.

### Task 3: Verify the new responsive behavior

**Files:**
- Reference only; no code changes.

- [ ] **Step 1: Start the app (`npm run dev`) and load the Conversations page with `selectedThread` visible.**
  - Expected: the CRM sidebar can toggle via the toolbar button or the `showContactSidebar` state.

- [ ] **Step 2: On a viewport narrower than 1280px, toggle the sidebar.**
  - Expected: the absolute overlay/backdrop appears, matching the previous behavior, and the new transition class smooths the slide-in/out animation.

- [ ] **Step 3: On an xl (=1280px) viewport, toggle the sidebar.**
  - Expected: the sidebar now renders as a flex sibling that pushes the email thread; the backdrop is hidden and both the sidebar panel and the row layout animate fluidly thanks to `transition-all duration-300 ease-in-out` on the panel container.

- [ ] **Step 4: Test long contact/lead/opportunity names and inspect the CRM panel height (`calc(100vh - 116px)`) in DevTools to verify `min-w-0`/`truncate` still prevent overflow and that the new layout doesn’t break the vertical sizing constraints.**

- [ ] **Step 5: Run `npm run lint` (or the existing lint/prettier pipeline) to confirm the new Tailwind classes don’t trigger warnings.**
  - Expected: lint command completes (or reports only pre-existing issues).
