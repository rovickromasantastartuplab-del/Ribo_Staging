# Design Spec: AI Strategic Summary Report Refactor

**Date**: 2026-04-13  
**Topic**: AI Summary Report Refactor (Template-Plus Mode)  
**Status**: READY FOR IMPLEMENTATION PLAN

## Goal
Enforce the supervisor PDF format from `docs/summary_tempate.pdf` while still allowing additional high-value context. The report must remain deterministic in structure across all scopes and never drop required sections.

## Confirmed Constraints
- Output format is `.pdf`.
- Rendering engine is `barryvdh/laravel-dompdf`.
- Backend generation remains synchronous.
- CRM metrics (ARR, MRR, deals, relationship and activity signals) must come from real scoped data, not fabricated model output.
- Mode is template-plus: strict template skeleton first, optional extras second.

## Canonical Report Skeleton (Strict)
The PDF always renders these sections, in this exact order:

1. `Client Account Snapshot`
2. `Account Status`
3. `Executive Insights`
4. `Key Relationships`
5. `Deals & Pipeline Snapshot`
6. `Engagement & Health Signals`
7. `Key Risks`
8. `Growth Opportunities`
9. `Recommended Actions (Next 30–60 Days)`

Optional section:
- `Additional Context` (render only when non-empty; never inserted between required sections)

## Scope Model
The same skeleton is used for all scopes:
- `overall`
- `leads-only`
- `all-opps`
- `specific-opportunity`

Only the data mapping changes by scope; layout does not.

## Data Mapping Rules
### Account Status
- `Status`: constrained to `Strategic | Growth | At Risk | Stable`.
- `Health Score`: constrained to `High | Medium | Low` plus one-line reason.
- `ARR | MRR | Renewal`:
  - `overall`: account aggregate.
  - `leads-only`: lead-level values when available; otherwise `Not available`.
  - `all-opps`: scoped opportunity aggregate.
  - `specific-opportunity`: selected opportunity values.

### Executive Insights
- 3-5 concise bullets.
- Each bullet must be commercially meaningful and tied to evidence.

### Key Relationships
- Deterministic table columns: `Name | Role | Type | Strength`.
- `Type` examples: `Decision-maker`, `Champion`, `Blocker`.
- `Strength` constrained to `Strong | Medium | Weak`.
- Always include a `Gaps:` line.

### Deals & Pipeline Snapshot
- Always render:
  - `Active Deals`
  - `Top Deal`
  - `Expansion Potential`
  - `Notable Past Deals` (`Won`, `Lost`, optional `Stalled`)
- `leads-only` renders explicit `Not available` where deal metrics are not in scope.

### Engagement & Health Signals
- Always render:
  - `Usage`
  - `Support`
  - `Sentiment`
  - `Engagement Pattern`

### Key Risks
- 2-4 specific and observable commercial/operational risks.

### Growth Opportunities
- 2-4 concrete opportunities tied to teams, products, use cases, or stakeholder pathways.

### Recommended Actions (Next 30–60 Days)
- Always include rows for:
  - `Sales`
  - `CSM`
  - `Support/Product`
  - `Exec Sponsor`
- Strict line pattern: `Role -> Action -> Priority`
- Priority constrained to `High | Medium | Low`.

## Guardrails (Validation Before Render)
Guardrails are deterministic backend checks that enforce policy and format:
- Enum checks for status and health values.
- Section cardinality checks (e.g., Executive Insights count).
- Action-line syntax checks (`Role -> Action -> Priority`).
- Scope-safety checks so data cannot leak outside selected scope.
- No section omission; missing content is replaced with `Not available`.
- Triage-consistency checks to avoid report optimism beyond triage state.

When validation fails:
- Apply normalization first.
- If still invalid, use controlled fallback text while preserving template structure.

## Prompt and Output Contract
`ReportPromptFactory` and `ReportSkill` will continue to use structured JSON, but the renderer will consume a normalized DTO-like structure produced by deterministic post-processing.

The AI can suggest content; backend owns final shape.

## Rendering Behavior
`resources/views/reports/ai_summary_pdf.blade.php` becomes a strict template renderer:
- Fixed headings and section order.
- Fixed table/bullet structures matching supervisor template pattern.
- Explicit placeholders for missing scoped fields.
- Optional `Additional Context` at the end only.

## Error Handling
- Missing scoped fields: render `Not available` rather than hiding rows/sections.
- No opportunities in scope: render explicit no-data lines under Deals & Pipeline Snapshot.
- AI schema/policy failure: preserve full section skeleton using fallback copy.
- Download endpoint behavior remains unchanged except for clearer surfaced validation failures.

## Test Strategy
### Unit
- Prompt/skill validation tests for enum/cardinality/action-format guardrails.
- Scope mapper tests for each scope (`overall`, `leads-only`, `all-opps`, `specific-opportunity`).
- Normalization tests that ensure deterministic fallback population.

### Feature
- API tests asserting scope enforcement and opportunity ownership validation.
- PDF payload tests asserting required section headings and ordering.
- Regression tests ensuring no section disappears when data is sparse.

### Manual QA
- Compare generated PDFs against `docs/summary_tempate.pdf` for structure parity.
- Validate that template sections remain fixed while content changes by scope.

## Out Of Scope
- Asynchronous job processing changes.
- New scoring models outside current triage + CRM source inputs.
- Frontend redesign beyond existing scope/opportunity controls.

## Success Criteria
- Required sections are always present and ordered exactly like the supervisor template.
- Output is consistent across all scopes, with data changes only.
- Sparse datasets still produce complete reports with explicit placeholders.
- Existing validation and safety constraints remain intact.
