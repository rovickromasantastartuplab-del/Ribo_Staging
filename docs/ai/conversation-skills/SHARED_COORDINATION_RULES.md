# Shared Coordination Rules

## Core Principle
Triage is the authoritative source of truth for the coordinated conversation AI system.

Triage owns:
- `thread_state`
- `relationship_health`
- `actionability`
- `behavioral_pulse`
- `success_probability`
- `strategic_action`

Downstream skills inherit triage truth. They explain it, preserve it, or express it. They do not override it.

## Shared Invariants
- If `thread_state = closed_lost`, no skill may imply active pursuit, healthy momentum, or an open selling motion.
- If `relationship_health = damaged`, no skill may use upbeat, pushy, or commercially aggressive language.
- If `thread_state = reopened`, all skills must stay cautious until concrete business motion confirms `active`.
- If `actionability = archive` or `do_not_pursue`, downstream outputs must avoid meetings, demos, quotes, and chase behavior.
- The latest message is the dominant signal for state transitions; earlier history adds context but does not overrule the latest message.

## Sender-Role Invariant
- Only inbound customer or prospect messages can revive a `closed_lost` thread.
- Outbound apologies, check-ins, or recovery attempts from our side do not count as revival.
- `reopened` is transitional. It can become `active` only after confirmed business motion, not from warmth alone.

## Downstream Skill Responsibilities
- Draft: render triage truth into customer-safe wording without becoming more optimistic than triage.
- Memory: preserve triage history and transitions over time without independently re-judging relationship reality.
- Report: explain triage truth to leadership, including why the state changed or stayed constrained.
- Strategic action: recommend one state-compatible next move; today this policy is emitted through triage rather than a standalone runtime prompt.

## Cross-Skill Alignment By State
- `objection`: Draft addresses the blocker first, Memory preserves the objection history, Report explains the blocker and risk shift, Strategic Action recommends blocker resolution before any commercial push.
- `misaligned`: Draft repairs scope, value, or process clarity before scheduling, Memory records structural friction, Report names the mismatch type, Strategic Action avoids meetings or quotes until alignment is repaired.
- `closed_lost`: Draft is blocked unless recovery is explicitly requested, Memory clamps relationship strength to weak, Report states the opportunity is closed lost plainly, Strategic Action stays internal and non-commercial.
- `reopened`: Draft uses one low-friction CTA, Memory preserves the revival event and stays cautious, Report highlights revival with caveats, Strategic Action recommends one careful follow-through step.
- `active`: Draft can advance the next step directly, Memory can preserve healthy momentum, Report can frame resumed business motion, Strategic Action can support concrete commercial progress.

## UI Alignment Guidance
- When triage fields are present, UI labels should render triage truth directly.
- Legacy derived logic should be bypassed when it would contradict triage state.
- Relationship and mood displays must not contradict `thread_state`, `relationship_health`, or `behavioral_pulse` from triage.
- Strategic action cards should reflect the triage-owned recommendation, not generate a conflicting interpretation client-side.

## Documentation Boundary
- Skill docs should describe the current implementation truthfully.
- If runtime behavior is currently enforced in `TriagePromptFactory` or `TriageSkill`, the docs should say so rather than implying a separate live subsystem exists.
- Future standalone prompt layers may extend these docs, but they must keep triage ownership intact.
