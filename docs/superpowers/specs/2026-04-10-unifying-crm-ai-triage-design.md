# Unified CRM AI Triage & State Management Design

## 1. Root Problem
Current CRM AI components (Triage, Draft, Memory, Report) operate as four independent intelligence calls. This fragmentation causes systemic incoherence: Triage might declare a deal dead while the Draft generator suggests a meeting, and the Executive Report remains optimistic. 

To fix this, **Triage must become the authoritative source of truth**. All other services must consume, respect, and align their outputs with the Triage state.

## 2. Shared State Model
Triage owns and determines the following canonical fields, stored in `AiTriageResult`:

| Field | Values | Ownership |
|---|---|---|
| **thread_state** | `active`, `nurturing`, `stalled`, `objection`, `misaligned`, `closed_lost`, `reopened`, `non_commercial`, `spam` | Triage |
| **relationship_health**| `positive`, `neutral`, `strained`, `damaged` | Triage |
| **actionability** | `act_now`, `monitor`, `archive`, `do_not_pursue` | Triage |
| **behavioral_pulse** | `heating_up`, `cooling_down`, `stable`, `broken` | Triage |
| **success_probability**| `integer (0–100)` | Triage |
| **strategic_action** | `JSON (goal, reason, recommendation)` | Triage |

### Key State Definitions & Transitions
- **stalled**: No response or ghosting pattern; recovery/re-engagement needed.
- **archive**: Commercially inactive, but not hostile.
- **do_not_pursue**: Trust broken, hostile, or explicit "stop contacting" signal.
- **reopened**: Transitional state from `closed_lost` triggered by explicit revival signals only. Move to `active` after one confirming business message.
- **Transition**: `active` ──► `objection` ──► `misaligned` ──► `closed_lost`.

## 3. Triage Redesign (The Engine)
Triage transitions from a snapshot classifier to a **state transition engine**.

### Prompt Updates (`TriagePromptFactory`)
- **Terminal Override**: Force `closed_lost`, `0-5%` prob, and `broken` pulse on rejection signals.
- **Revival Override**: Detect explicit re-engagement (e.g., "let's proceed"). Trigger `reopened` state with `25-45%` probability.
- **Escalation & Latest-Message Priority**: Latest message is the dominant signal. History informs but cannot override a clear reversal.

### PHP Enforcement (`TriageSkill`)
- **Revival Handling**: If previous state was `closed_lost` and AI says `reopened`, clamp probability and force `act_now`.
- **Escalation Guard**: Prohibit success probability increases if an `objection` is repeated and unresolved.

## 4. Draft Follows Triage (The Follower)
Draft generation is now constrained by the Triage state to prevent "Sales Optimism" in dead threads.

### Implementation
- **Data Flow**: `DraftService` loads the latest `AiTriageResult` and passes it to `DraftSkill`.
- **PHP Guards**:
    - Block drafts for `do_not_pursue`.
    - Block `closed_lost` unless instruction contains recovery keywords (e.g., "win-back").
    - `misaligned` guard: Force repair of clarity/scope before allowing scheduling language.
- **Prompt Injection**: Authoritative injection of Triage state. Instruction: *"Use triage state as the source of truth. Do not infer a more optimistic state from the thread."*
- **Fallback**: Neutral "We'll be in touch" instead of scheduling pushes.

## 5. Memory Follows Triage (The Historian)
Memory becomes a relationship historian that records state transitions.

### Implementation
- **Data Flow**: Pass a historical array of recent Triage results to `MemorySkill`.
- **Prompt Injection**: Authoritative triage history block. Instruction: *"Prefer state patterns over isolated message wording."*
- **PHP Reconciliation**:
    - `closed_lost` or `damaged` → Clamp `relationship_strength` to `weak`.
    - `reopened` → Max `moderate`.
- **New Insights**: Capture state changes (e.g., "Deal was closed_lost, now reopened") and growth signals (e.g., "Consistent positive engagement").

## 6. Report Follows Triage (The Communicator)
Reports explain the Triage decision to leadership rather than re-judging the thread.

### Implementation
- **Framing**: Reports must frame summaries around the Triage state.
- **Next Actions Gating**: Strip commercial actions (meetings/quotes) if `actionability` is `archive` or `do_not_pursue`. 
- **Misaligned Insights**: Must explicitly explain the *type* of mismatch (scope, value, etc.).
- **Snapshot Storage**: Store the Triage state in `AiReportJob` payload at time of generation.

## 7. UI Consistency mapping
UI labels and buttons must align with Triage state.

| Signal | UI Display / Rule |
|---|---|
| **thread_state** | Map to specific labels (e.g., `closed_lost` → "Closed Lost"). |
| **reopened** | Must not show 0%, "Lost" label, or `broken` pulse. |
| **behavioral_pulse** | First-class support for `broken` (Red X icon). |
| **Button Gating** | Hide "Schedule Meeting" or "Draft Reply" if `do_not_pursue`. |
| **Mood Source** | Use `relationship_health` (Triage) as primary source, `strength` (Memory) as fallback. |

## 8. Code Change Plan
1. **`TriagePromptFactory`**: Update enum, add rules for Terminal, Revival, and Escalation.
2. **`TriageSkill`**: Add PHP enforcement for revival and escalation; read previous triage result.
3. **`DraftSkill`**: Add PHP guards for terminal states and context injection.
4. **`MemorySkill`**: Add triage history injection and PHP reconciliation for strength clamping.
5. **`ReportSkill`**: Add framing instructions and next-action stripping.
6. **`mockAiData.ts`**: Update TS interfaces and add mapping/gating utilities.
7. **`ConversationAiTriageService`**: Update to pass previous triage to Skill.

## 9. Example Flow: Terminal → Revival

### Scenario A: Rejection
- **Customer**: "We've decided to step back for now."
- **Triage Result**: `thread_state: closed_lost`, `actionability: archive`, `pulse: broken`, `prob: 0%`.
- **Draft**: Blocked (unless "recovery" asked).
- **Report**: Summary starts with `[CLOSED LOST]`.
- **UI**: Status "Closed Lost", mood "Neutral", actions limited to Archive.

### Scenario B: Revival (Later)
- **Customer**: "Apology accepted, let's proceed to our business."
- **Triage Result**: `thread_state: reopened`, `actionability: act_now`, `pulse: heating_up`, `prob: 35%`.
- **Draft**: Allowed (low-friction, gentle one-step CTA).
- **Report**: Summary starts with `[REOPENED — PROCEED WITH CAUTION]`.
- **UI**: Status "Reopened", mood "Positive", recovery actions enabled.
