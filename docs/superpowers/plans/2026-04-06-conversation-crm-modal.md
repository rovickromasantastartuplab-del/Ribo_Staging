# Conversation CRM Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the conversation CRM right sidebar with a centered modal dialog that opens from the existing header button, preserving Lead/Opportunity content and improving sizing/spacing.

**Architecture:** Reuse the existing CRM sidebar markup, but render it inside the shared Dialog component. Keep the existing tab state and data logic; only change the container and layout classes. Use the existing header button to open/close the dialog.

**Tech Stack:** React, TypeScript, Tailwind CSS, ShadCN Dialog

---

## File Structure

- Modify: `C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx`
  - Replace `showContactSidebar` state with modal open state
  - Swap sidebar container markup for `Dialog` and `DialogContent`
  - Adjust sizing and spacing classes for larger modal

---

### Task 1: Switch CRM visibility state to modal state

**Files:**
- Modify: `C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx`

- [ ] **Step 1: Replace sidebar state with modal state**

```tsx
// BEFORE
const [showContactSidebar, setShowContactSidebar] = useState(false);

// AFTER
const [showCrmModal, setShowCrmModal] = useState(false);
```

- [ ] **Step 2: Update header button toggle to open modal**

```tsx
<Button
  variant="ghost"
  size="icon"
  className="h-8 w-8"
  onClick={() => setShowCrmModal(true)}
>
  <User className={`h-4 w-4 ${showCrmModal ? 'text-primary' : ''}`} />
</Button>
```

- [ ] **Step 3: Update any close handlers**

```tsx
<Button
  variant="ghost"
  size="icon"
  className="h-7 w-7 shrink-0"
  onClick={() => setShowCrmModal(false)}
>
  <X className="h-4 w-4" />
</Button>
```

- [ ] **Step 4: Commit**

```bash
git add C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx
git commit -m "feat: switch CRM sidebar state to modal"
```

---

### Task 2: Replace sidebar container with centered modal dialog

**Files:**
- Modify: `C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx`

- [ ] **Step 1: Remove sidebar backdrop and sliding container**

```tsx
// Remove the mobile backdrop and the absolute right sidebar wrapper
// (the block starting near the "Pane 4: CRM Context Sidebar" comment)
```

- [ ] **Step 2: Wrap CRM content in Dialog + DialogContent**

```tsx
<Dialog open={showCrmModal} onOpenChange={setShowCrmModal}>
  <DialogContent className="max-w-[900px] w-[92vw] max-h-[85vh] p-0 overflow-hidden">
    {/* CRM header, tabs, and ScrollArea content go here */}
  </DialogContent>
</Dialog>
```

- [ ] **Step 3: Keep existing CRM header + tabs + body content unchanged**

```tsx
<div className="flex items-center justify-between gap-2 min-w-0 px-5 py-4 border-b">
  {/* header content */}
</div>
<div className="flex border-b">
  {/* tab buttons */}
</div>
<ScrollArea className="flex-1 min-h-0">
  {/* Lead / Opportunity sections */}
</ScrollArea>
```

- [ ] **Step 4: Commit**

```bash
git add C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx
git commit -m "feat: move CRM sidebar into modal dialog"
```

---

### Task 3: Adjust spacing and sizing for the larger modal

**Files:**
- Modify: `C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx`

- [ ] **Step 1: Increase inner padding and spacing**

```tsx
// Example adjustments
<div className="p-6 space-y-5"> // Lead or Opportunity container
```

- [ ] **Step 2: Ease truncation where possible**

```tsx
// Example: allow more room on value rows
<span className="min-w-0 flex-1 text-right font-semibold text-emerald-600">
```

- [ ] **Step 3: Commit**

```bash
git add C:\Users\Rovick\Downloads\Final_Production\resources\js\pages\conversations\index.tsx
git commit -m "style: improve CRM modal spacing"
```

---

### Task 4: Manual QA

- [ ] **Step 1: Run the app and open a conversation**
- [ ] **Step 2: Click the CRM header button and confirm the modal opens**
- [ ] **Step 3: Switch Lead/Opportunity tabs and verify content**
- [ ] **Step 4: Expand/collapse an opportunity and confirm details render**
- [ ] **Step 5: Close modal by X and by clicking the overlay**

---

## Self-Review Checklist

1. **Spec coverage:** All sections are covered by Tasks 1–4 (modal on all sizes, same tabs, improved sizing/spacing, same triggers).
2. **Placeholder scan:** No TBD/TODO placeholders remain.
3. **Type consistency:** `showCrmModal` state name is used consistently across toggle and close handlers.
