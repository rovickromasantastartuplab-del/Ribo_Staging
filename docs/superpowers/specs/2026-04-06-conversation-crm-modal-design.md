# Conversation CRM Modal Design

Date: 2026-04-06

## Summary
Replace the existing right-side CRM context sidebar on the conversation page with a centered modal dialog that opens from the existing conversation header button. The modal contains the same Lead and Opportunity tabs and content as today, but uses a larger, more comfortable size to take advantage of the extra space.

## Goals
- Free up horizontal space by removing the persistent right sidebar.
- Preserve all Lead/Opportunity functionality and behavior.
- Improve readability and spacing inside the CRM context area.

## Non-Goals
- Redesigning Lead/Opportunity content or data structures.
- Changing permission logic or API behavior.
- Introducing new CRM features.

## Current State
- The CRM context is a right-side sliding panel rendered in `resources/js/pages/conversations/index.tsx`.
- The panel includes a header with contact info and a close button, tabbed sections for Lead and Opportunity, and a scrollable content body.

## Proposed Changes
### 1) Replace Sidebar With Centered Dialog
- Remove the right-side pane layout and render the CRM context in a modal dialog.
- The dialog opens via the existing conversation header button (same trigger as today).
- The modal is used on all screen sizes (no sidebar on desktop).

### 2) Dialog Layout
- Header: contact avatar/name/company on the left, close button on the right.
- Tabs: Lead and Opportunity tabs remain directly below the header.
- Body: scrollable area containing Lead/Opportunity content.

### 3) Sizing and Spacing
- Use a wider modal (e.g., `max-w-[900px]`) with a generous height (e.g., `max-h-[85vh]`).
- Increase internal padding and spacing to reduce cramped layouts.
- Allow longer text lines before truncation where feasible.

## Interaction & State
- Replace `showContactSidebar` with `showCrmModal` (or similar) as the open/close state.
- Keep existing tab state (`activeSidebarSection`) as-is.
- Close actions:
  - Close button in header.
  - Click outside the dialog (default dialog behavior).

## Component/Files Impacted
- `resources/js/pages/conversations/index.tsx`
  - Remove sidebar layout container.
  - Render CRM content in a dialog component.
  - Update open/close state naming.
  - Adjust classes for modal sizing and spacing.

## Accessibility
- Dialog must trap focus and be dismissible via Escape key (use existing dialog component behavior).
- Keep clear focus styles for tab buttons and action buttons.

## Testing
- Manual QA:
  - Open and close the CRM modal via header button.
  - Switch tabs and verify content is unchanged.
  - Validate scrolling inside the modal body.
  - Confirm opportunity expand/collapse still works.
  - Verify permission-based controls still behave the same.

## Open Questions
- None.
