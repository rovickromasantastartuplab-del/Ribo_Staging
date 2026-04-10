# Design Spec: UI De-Cluttering & Redundancy Removal

**Date**: 2026-04-10
**Topic**: Conversation AI Side Panel UX Refinement
**Status**: Approved

## Objective
To reduce visual noise and eliminate redundant data points in the Conversation AI side panel. The sticky header in `ConversationAiPanel` now contains the authoritative state info, making internal card headers and duplicate badges redundant.

## Proposed Changes

### 1. AiTriageCard.tsx
*   **Remove Header**: Delete the `<CardHeader>` section.
*   **Remove State Row**: Delete the `div` containing State, Relationship, and Actionability badges.
*   **Remove Next Steps**: Delete the `div` section for "Allowed Next Steps".
*   **Preserve**: Intent, Priority, and Confidence badges + the Summary text and action buttons.

### 2. AiStrategicActionCard.tsx
*   **Remove Header**: Delete the `<CardHeader>` section.
*   **Remove UI-Safe Actions**: Delete the "UI-Safe Actions" section.
*   **Preserve**: The recommendation text and goal/reason info.

## Success Criteria
- Side panel feels less "busy" while retaining all core analytical data in the top header.
- No functional regressions in summary report generation or state updates.
