# Design Spec: AI Strategy Drawer (Intelligence Wing)

## 🎯 Purpose
To transition the "AI Sidekick" from a tabbed modal into a persistent **"Intelligence Wing"** at the far left of the Conversations Inbox. This makes strategy and accountability (Task Checklists) the foundational first step of every message interaction, as requested by the Founder.

## 📐 Architecture: The Left-Column Drawer
The design shifts from an overlaying Dialog to a structural column within the main Conversations layout.

### 1. Positioning & Hierarchy
The new layout order (Left to Right):
1. **App Nav**: (Existing fixed icons).
2. **AI Strategy Drawer**: (The NEW "Intelligence Wing").
3. **Thread List**: (Existing inbox list).
4. **Message Workspace**: (Existing thread content).

### 2. Dual-State Drawer UI
- **Closed State (Trigger Bar)**:
    - **Width**: 52px.
    - **Visuals**: A slim vertical strip with a `glassmorphism` blur effect.
    - **Quick Glance Icons**: 
        - `Target` icon with the Deal Probability % (e.g., "85%").
        - `Activity` pulse icon (Heartbeat) for "Mood."
    - **Action**: Clicking anywhere or a `ChevronRight` expands the drawer.
- **Open State (Full Panel)**:
    - **Width**: 320px.
    - **Content**: The full `ConversationAiPanel` (Tasks, Summary, Smart Reply).
    - **Action**: A `ChevronLeft` or "X" collapses it back to the trigger bar.

### 3. Interaction & Responsiveness
- **Insertion**: "Use this reply" button in the AI panel continues to insert text into the active message editor on the right.
- **Responsive**: On screens < 1024px, the drawer disappears to preserve space for reading, with an optional "Mobile Toggle" if needed.

## 📊 Technical Requirements
- **Target File**: `resources/js/pages/conversations/index.tsx`.
- **State Management**: New React state `isAiDrawerOpen` (boolean).
- **CSS Transitions**: Tailwind `transition-all duration-300 ease-in-out` for width changes.

## 🏁 Success Criteria
- The "Task Checklist" and "Mood" are visible or 1-click away at all times.
- The thread list remains fully interactive even when the AI wing is open.
- The UI feels "World-Class" and premium, matching the Founder's "Sidekick" vision.
