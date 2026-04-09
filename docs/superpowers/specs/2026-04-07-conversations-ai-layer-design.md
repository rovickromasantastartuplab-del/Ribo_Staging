# Design Spec: Conversations AI Layer (Phase 4 - Strategic Sidekick)

## 🎯 Purpose
To implement "Phase 4 — AI Layer" (V1.0) by transitioning the AI Sidekick from a purely analytical tool into a proactive **Strategic Consultant**. This phase focuses on predictive deal scoring, behavioral intelligence, and goal-oriented reply generation.

## 📐 Architecture: Approach 3 (Embedded Strategy)

The design prioritizes **seamless action** by integrating strategic advice directly into the reply drafting workflow.

### 1. Intelligence Strip (Top-Level)
A slim, visual row at the top of the AI Sidekick panel for immediate context:
- **Win Probability Badge**: Dynamic percentage (e.g., "🎯 85% Deal Probability").
- **Behavioral Pulse Badge**: Engagement velocity indicator (e.g., "🔥 Heating Up" with trend data).

### 2. Strategic Reply Assistant (`AiReplyAssistantCard.tsx`)
The centerpiece of Phase 4. It now links the AI's "Thought" to the "Action":
- **Strategic Recommendation**: A new prominently highlighted section (Indigo/Purple) within the card.
- **Goal-Reason-Action Logic**: Displays the AI's reasoning (e.g., "Goal: Negotiate; Reason: Competitor mention; Action: Offer discount").
- **Strategy-Aware Drafts**: The "Generate" button uses the `strategic_move` to influence the mock draft content.

### 3. Forensic Context (Secondary)
The `AiTriageCard` and `AiMemoryCard` remain as backup context below the action area, providing the "Why" behind the AI's strategy.

## 📊 Data Interface (Mock Updates)
Update the `mockAiData.ts` utility to return:
- `success_probability`: (number) 0-100.
- `behavioral_pulse`: ('heating_up', 'cooling_down', 'stable').
- `strategic_move`: { goal: string, reason: string, recommendation: string }.

## 🏁 Success Criteria
- The agent understands the **Winning Strategy** within 2 seconds of opening the AI tab.
- The **Magic Draft** reflects the strategic advice (e.g., if AI says "give discount," the draft contains a discount).
- The "Sidekick" feels like a proactive partner rather than a static summary.
