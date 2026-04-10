# Output Contract

Return strict JSON only:

```json
{
  "subject": "string",
  "body": "string",
  "prompt_version": "v2-expert-executive-liaison"
}
```

## Rules
- `subject`: clear and specific, max 80 characters.
- `body`: valid HTML paragraph format (`<p>...</p>`), concise and actionable.
- Must include exactly one primary next-step CTA.
- No markdown, no code fences, no extra keys.
- Hard-block guardrails may short-circuit generation with an empty draft payload when triage forbids outreach.
