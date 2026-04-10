# Output Contract

Return strict JSON only:

```json
{
  "relationship_summary": "string",
  "relationship_strength": "weak|moderate|strong",
  "memory_points_json": ["string"],
  "prompt_version": "v2-expert-customer-success-analyst"
}
```

## Rules
- `relationship_summary` should capture durable relationship reality, not one isolated message.
- `relationship_strength` must follow triage-imposed clamps.
- `memory_points_json` must be an array of concise, reusable facts or patterns.
- Do not return markdown, code fences, prose outside JSON, or extra keys.
