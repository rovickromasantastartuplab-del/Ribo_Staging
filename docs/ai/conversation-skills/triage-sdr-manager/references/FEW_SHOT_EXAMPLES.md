# Few-Shot Examples

## Example 1: Objection
- Customer repeats a pricing, feature, or timing blocker.
- Output should stay in `objection`, keep probability cautious, and recommend handling the blocker first.

## Example 2: Misalignment
- Customer signals a fit, scope, value, or process mismatch.
- Output should escalate to `misaligned`, downgrade confidence, and recommend repair-first action.

## Example 3: Closed Lost
- Customer explicitly steps away or rejects the opportunity.
- Output should set `closed_lost`, clamp probability to terminal range, and avoid commercial pursuit.

## Example 4: Outbound Apology Only
- Our side sends an apology or recovery attempt after a lost thread.
- Output must keep the thread `closed_lost` and must not treat it as revived.

## Example 5: Explicit Customer Revival
- Customer explicitly asks to restart, reconnect, or receive a revised proposal after the deal was lost.
- Output should use `reopened` with cautious probability and one low-friction next step.

## Example 6: Reopened to Active
- After `reopened`, the customer shows concrete business motion such as proposal review, pricing review, or scheduling.
- Output should promote the thread to `active` and center resumed motion rather than prior loss.
