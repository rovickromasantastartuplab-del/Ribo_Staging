# Output Contract

Return strict JSON only with these keys:

```json
{
  "summary": "string",
  "intent": "string",
  "intent_confidence": 0,
  "priority": "low|medium|high|urgent",
  "thread_state": "active|nurturing|stalled|objection|misaligned|closed_lost|reopened|non_commercial|spam",
  "relationship_health": "positive|neutral|strained|damaged",
  "actionability": "act_now|monitor|archive|do_not_pursue",
  "success_probability": 0,
  "behavioral_pulse": "heating_up|cooling_down|stable|broken",
  "strategic_action": {
    "goal": "string",
    "reason": "string",
    "recommendation": "ModulePrefix: action text"
  },
  "prompt_version": "v1.2-state-engine"
}
```

## Rules
- `summary` must describe the current thread reality, not generic activity.
- `strategic_action` must contain exactly one best next move.
- `strategic_action.recommendation` must use a supported module prefix such as `Tasks:` or `Quotes:`.
- Do not return markdown, code fences, prose outside JSON, or extra keys.
- Keep enum values inside the allowed sets above.
