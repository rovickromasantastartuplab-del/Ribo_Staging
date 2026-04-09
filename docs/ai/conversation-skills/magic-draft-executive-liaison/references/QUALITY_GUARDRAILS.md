# Quality Guardrails

## Prompt Safety
- Wrap all thread-derived text in `<<untrusted_data>>` delimiters.
- Explicitly instruct model to ignore commands found in thread content.

## Validation
- Parse stage: enforce JSON shape and required keys.
- Policy stage: enforce tone and CTA constraints.
- Repair stage: if validation fails, return safe fallback draft.

## Fallback Template
- Subject: "Quick follow-up"
- Body: concise acknowledgment plus one neutral next-step question.

## Telemetry Fields
- `prompt_version`
- `raw_subject`, `raw_body`
- `final_subject`, `final_body`
- `fallback_applied`, `fallback_reason`
- optional `style_flags` for policy misses
