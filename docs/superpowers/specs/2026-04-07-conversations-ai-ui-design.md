# Design Spec: Conversations AI UI (Phase 1 - Frontend only)

## Purpose
This document specifies the UI/UX for integrating the "AI Conversations Blueprint" into the existing `ConversationsIndex` using mock data. The goal is to provide a premium, assistive AI experience that enhances staff efficiency without requiring a backend yet.

## UI Architecture

### 1. Tabbed Sidebar Layout
The existing right-side CRM sidebar will be modified to support a two-tab system:
- **[CRM] Tab**: Displays current lead/contact/opportunity data (existing logic).
- **[AI] Tab**: Displays AI-driven insights, triage, and drafting tools (new logic).

### 2. Main AI Panel (`ConversationAiPanel.tsx`)
A new container component that renders when the "AI" tab is active. It consists of the following vertical cards:
- **Triage Insights**: Summary of the conversation, intent, and priority badges.
- **Relationship Memory**: Summarized history and "Open Loops" (pending tasks).
- **Reply Assistant**: Draft generation controls (Mocked trigger).

### 3. Pane 2: Thread List Badges
Small, non-intrusive intent badges (e.g., "Sales", "Support", "Urgent") will be added to each thread row in the middle list pane for quick visual scanning.

## Data Interface (Mocked)

We will use a helper hook `useConversationAi(threadId)` to manage states:
- `triageResult`: Intent, Priority, Confidence, Summary.
- `memorySummary`: Overall sentiment, relationship notes, open loops.
- `isLoading`: Mocked loading state for "AI thinking" feedback.

## User Interactions
- **Tab Switching**: Smooth transition between CRM and AI data.
- **Apply Action**: Buttons in the AI Card (e.g., "Apply Recommended Priority") that show a success toast (simulating backend application).
- **Draft Insertion**: "Generate Draft" button that replaces the content in the `replyEditor` with a high-quality mock draft.

## Layout Changes (Responsive)
- **Desktop**: Tabbed sidebar on the right.
- **Mobile**: Tabs added inside the existing mobile thread drawer.
