# Output Contract

This policy currently ships inside triage output as the nested `strategic_action` object:

```json
{
  "goal": "string",
  "reason": "string",
  "recommendation": "ModulePrefix: action text"
}
```

## Rules
- `goal` should name the single objective of the next move.
- `reason` should explain why the action matches the triage state.
- `recommendation` should use one module-prefixed instruction such as `Tasks:` or `Quotes:`.
- Do not return multiple options or a plan list.
- Do not contradict triage-owned `thread_state`, `relationship_health`, or `actionability`.
