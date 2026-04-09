# Output Contract

Return strict JSON only:

```json
{
  "subject": "string",
  "body": "string"
}
```

## Rules
- `subject`: clear and specific, max 80 characters.
- `body`: valid HTML paragraph format (`<p>...</p>`), concise and actionable.
- Must include exactly one primary next-step CTA.
- No markdown, no code fences, no extra keys.
