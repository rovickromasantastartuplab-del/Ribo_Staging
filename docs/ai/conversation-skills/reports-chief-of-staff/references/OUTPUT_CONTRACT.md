# Output Contract

Return strict JSON only:

```json
{
  "summary": "string",
  "key_insights": ["string"],
  "next_actions": ["string"],
  "prompt_version": "v2-expert-chief-of-staff"
}
```

## Rules
- `summary` should be executive-ready and triage-led.
- `key_insights` should explain why the state is what it is, not repeat raw thread text.
- `next_actions` must stay aligned with triage actionability.
- Do not return markdown, code fences, prose outside JSON, or extra keys.
