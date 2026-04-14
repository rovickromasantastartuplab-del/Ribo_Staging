# Design Spec: Conversation AI Summary PDF Redesign (Analytics-First)

Date: 2026-04-15
Owner: Conversations / AI
Status: Draft for user review

## 1. Goal
Redesign the Conversation AI Summary PDF into a two-page, light-theme report where:
- Page 1 prioritizes relationship analytics graphs.
- Page 2 presents executive summary content.
- Visual styling aligns with the system branding automatically.

## 2. Locked Decisions
- Report format remains PDF rendered via DomPDF.
- Layout is fixed at 2 pages (analytics first, summary second).
- Light-theme output only.
- Brand accent color is dynamic from system brand settings (not hardcoded).
- Graph date range is dynamic based on available activity (30/60/90-day window logic).

## 3. Information Architecture

### 3.1 Page 1: Analytics Dashboard
Sections in order:
1. Report header strip
2. Top 3 Takeaways
3. User Relationship Health Graphs

Graph set (in order):
1. Interaction volume over time (emails/replies/meetings)
2. Average response time trend
3. Relationship strength trend (Strong/Medium/Weak)
4. Inactivity gaps (days since last meaningful touch)
5. Sentiment trend (positive/neutral/negative)

### 3.2 Page 2: Executive Summary
Sections in order:
1. Client Account Snapshot
2. Account Status
3. Executive Insights
4. Key Relationships
5. Deals & Pipeline Snapshot
6. Key Risks
7. Growth Opportunities
8. Recommended Actions (Role -> Action -> Priority)
9. Evidence Trace

## 4. Data and Rendering Rules

### 4.1 Dynamic Date Window
- Use activity coverage to choose chart window:
  - 90 days when sufficient historical activity exists.
  - 60 days when medium coverage exists.
  - 30 days when sparse/short history exists.
- X-axis uses explicit dates (`MMM dd`).
- Use weekly tick labels for readability.
- Missing days remain in-series (zero/flat), never dropped.

### 4.2 Event Markers
- Render dated markers for major milestones where available:
  - Proposal sent
  - Escalation opened
  - Renewal milestone
  - Key outbound follow-up
- Marker labels include both event and date.

### 4.3 Top 3 Takeaways
- Deterministic summary bullets generated from normalized analytics output.
- Prioritize one momentum insight, one risk insight, and one action-oriented insight.
- If source data is sparse, fallback copy is shown explicitly (no empty strip).

### 4.4 Brand Styling (Dynamic)
- Resolve brand theme color from existing system/global settings used by the app.
- Pass normalized `brandColor` into PDF view at generation time.
- Derive print-safe variants for:
  - Accent fill
  - Accent border
  - Chart primary stroke
- Fallback to default brand color only when setting is absent/invalid.

## 5. Visual Design Direction (Light Theme)
- Keep neutral light background with high-contrast text for print fidelity.
- Use one accent family derived from dynamic brand color.
- Typographic hierarchy:
  - Strong page title
  - Section headers with subtle tint bars
  - Compact body text for dense data blocks
- Chart cards use consistent spacing, axis treatment, and legend language.
- Avoid dark backgrounds, gradients, or heavy color fills that reduce print legibility.

## 6. Backend / Template Responsibilities

### 6.1 Formatter Responsibilities
Add normalized analytics payload fields required by the new page 1 graph block:
- date labels and series per graph
- chosen date window metadata
- event marker list
- top three takeaway lines

### 6.2 Controller Responsibilities
- Resolve brand settings for current company context.
- Pass `brandColor` and derived style tokens to Blade.
- Maintain existing authorization and download/version behavior.

### 6.3 Blade Template Responsibilities
- Render deterministic page structure (page 1 analytics, page 2 summary).
- Keep all required sections present; fallback text for missing values.
- Ensure page-break behavior is explicit and stable in DomPDF.

## 7. Error Handling and Fallbacks
- Missing analytics inputs:
  - Render graphs with empty baseline + explicit "Not available" annotation.
- Missing marker dates:
  - Omit marker point but preserve graph layout.
- Invalid brand color:
  - Use fallback color and continue render (no hard failure).
- Sparse datasets:
  - Keep full section skeleton with controlled placeholders.

## 8. Test Strategy

### 8.1 Feature / Template Tests
- Page 1 contains all 5 graph section titles in fixed order.
- Page 2 contains required summary sections in fixed order.
- Top 3 Takeaways strip always appears with either data-driven or fallback lines.
- Date axis includes formatted date labels.
- Event markers render when events exist.
- Dynamic brand color token is injected into rendered HTML.

### 8.2 Regression Tests
- Existing download endpoints and history/version flows remain unchanged.
- Cross-company authorization boundaries remain enforced.
- Sparse context still produces full 2-page structure without section loss.

## 9. Out of Scope
- Frontend modal redesign for history UI.
- New AI model prompt schema redesign beyond fields needed for analytics normalization.
- Multi-theme PDF variants (dark mode is excluded for this iteration).

## 10. Success Criteria
- Generated PDF consistently renders as a 2-page report.
- Page 1 clearly communicates relationship health trends with dated axes.
- Page 2 remains executive-actionable and structurally complete.
- Brand updates by superadmin are reflected in new PDF downloads without code changes.
