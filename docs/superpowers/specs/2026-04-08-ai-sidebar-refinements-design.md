# Design Spec: AI Sidebar Refinements

## Background
The user wants to refine the AI sidebar components to be more professional and "passive" in their guidance, while also expanding the reporting capabilities to include a "Full Activity" view.

## Proposed Changes

### 1. AI Strategic Action (Passive Card)
- **Component**: `AiStrategicActionCard.tsx`
- **Design**: Remove the "Apply Strategy" button.
- **Goal**: Transform the card from an interactive tool into a "Strategic HUD" component that provides wisdom without requiring a click.

### 2. AI Summary Reports (Tiered Selector)
- **Component**: `AiTriageCard.tsx`
- **Requirement**: Add 4 distinct tiers of report generation.
- **Dropdown Options**:
    1. **Full Activity History** (Value: `overall`): Summarizes Leads + All Opportunities for the contact.
    2. **Contact Interaction History** (Value: `lead-only`): Just the lead's activity stream.
    3. **Combined Opportunity Summary** (Value: `all-opps`): Summarizes all deals/projects for this contact.
    4. **[Individual Opportunity Names]**: Selective focus on a single deal.

## Architecture & Data Flow
- These changes are primarily UI/Display changes.
- The `Select` value `overall` will trigger the existing "AI is generating your summary report..." toast logic as a prototype simulation.

## Verification
- Verify the `AiStrategicActionCard` has no button.
- Verify the `AiTriageCard` dropdown has all 4 tiers in the correct order.
