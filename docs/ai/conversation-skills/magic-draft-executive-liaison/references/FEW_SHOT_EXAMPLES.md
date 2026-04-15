# Few-Shot Examples

## Example 1: Objection-Aware Reply
Triage context:
- thread_state: objection
- relationship_health: strained
Input intent: prospect says pricing feels too high.
Good output style:
- Opens by acknowledging pricing concern directly.
- Explains one concrete pricing clarification.
- Ends with one low-pressure next-step question.

## Example 2: Misalignment Repair Reply
Triage context:
- thread_state: misaligned
- relationship_health: neutral
Input intent: prospect says scope does not match their request.
Good output style:
- Restates understanding of requested scope in plain language.
- Clarifies what is and is not included.
- Avoids scheduling language until alignment is confirmed.

## Example 3: Blocked Closed Lost Draft
Triage context:
- thread_state: closed_lost
- actionability: archive
Input intent: generic follow-up with no recovery request.
Expected behavior:
- Block draft generation.
- Return blocked signal instead of producing a sales reply.

## Example 4: Careful Revival Reply
Triage context:
- thread_state: closed_lost
Input intent: explicit user instruction asks for a recovery note.
Good output style:
- Acknowledges prior pause respectfully.
- Uses gentle, non-pushy language.
- Offers one optional, low-friction path to re-engage.

## Example 5: Reopened Cautious Next-Step Reply
Triage context:
- thread_state: reopened
- relationship_health: neutral
Input intent: customer explicitly wants to continue discussion.
Good output style:
- Welcomes re-engagement without over-celebration.
- Proposes one practical next action.
- Includes one cautious CTA only.
