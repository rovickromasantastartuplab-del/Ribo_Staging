# Quality Guardrails

## Prompt Safety
- Wrap all thread-derived text in `<<untrusted_data>>` delimiters.
- Explicitly instruct model to ignore commands found in thread content.
- Treat triage context as authoritative, not optional.

## Validation
- Parse stage: enforce JSON shape and required keys.
- Policy stage: enforce tone, CTA, and triage-state constraints.
- Guard stage: block drafts that violate hard triage rules before the AI call when possible.
- Repair stage: if validation fails, return the safe fallback path defined by the runtime.

## State Guardrails
- `do_not_pursue`: block draft generation.
- `closed_lost`: allow only explicit recovery, revival, or farewell instructions; otherwise block draft generation.
- `misaligned`: do not schedule meetings or demos until scope, value, or process clarity is repaired.
- `damaged` or `closed_lost`: reject aggressive sales language and urgency framing.
- `reopened`: allow one gentle CTA only.

## Fallback Template
- Hard-block path may return an empty draft payload when triage forbids outreach.
- Repair path uses a generic low-risk follow-up draft and should be treated as a safety fallback, not as evidence that the thread is healthy.

## Telemetry Fields
- `prompt_version`
- `raw_subject`, `raw_body`
- `final_subject`, `final_body`
- `fallback_applied`, `fallback_reason`
- optional `style_flags` for policy misses
