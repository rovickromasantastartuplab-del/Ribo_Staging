# Debug Spec: [Bug Name]
**Type:** Debugging
**Created:** [Date and Time]
**Status:** Draft / Investigating / Root Cause Found / Approved for Fix / Complete

---

## 1. Bug Summary

### Problem
*Describe the bug clearly.*

### Expected Behavior
*What should happen?*

### Actual Behavior
*What is happening instead?*

### Impact
- [ ] User-visible issue
- [ ] Data issue
- [ ] Realtime / async issue
- [ ] Performance issue
- [ ] Security issue
- [ ] Other: [describe]

---

## 2. Reproduction

### Reproduction Steps
1.
2.
3.

### Reproducibility
- [ ] Always
- [ ] Sometimes
- [ ] Not yet reproduced reliably

### Notes
*Document environment, account type, timing, or other conditions.*

---

## 3. Trace the Flow

Describe the relevant system path end-to-end.

### Broken Path
*Trace the path where the issue occurs.*

### Working / Expected Path
*Trace the path that should work, or a similar working path.*

### Relevant Files / Modules / Services
- [ ] File/module/service 1
- [ ] File/module/service 2
- [ ] File/module/service 3

---

## 4. Hypotheses

List and test one hypothesis at a time.

### Hypothesis 1
- Description:
- Evidence for:
- Evidence against:
- Result: Confirmed / Rejected / Inconclusive

### Hypothesis 2
- Description:
- Evidence for:
- Evidence against:
- Result: Confirmed / Rejected / Inconclusive

### Hypothesis 3
- Description:
- Evidence for:
- Evidence against:
- Result: Confirmed / Rejected / Inconclusive

---

## 5. Root Cause

**Do not proceed to a fix until this section is complete and approved.**

### Proven Root Cause
*State the exact cause with evidence.*

### Exact Breakpoint
*Where does the system stop behaving correctly?*

### Evidence
*Logs, trace observations, code path findings, or comparison results.*

---

## 6. Fix Plan Approval

### Smallest Safe Fix
*Describe the minimal change that should resolve the root cause.*

### Affected Files / Modules
- [ ] File/module 1
- [ ] File/module 2

### Risks
- [ ] Regression risk identified
- [ ] Edge cases considered

### Approval
- [ ] User approved fix plan

---

## 7. Minimal Fix

- [ ] Implement only the approved fix
- [ ] Avoid unrelated refactors
- [ ] Add/update regression test if appropriate

### Notes
*Document what changed.*

---

## 8. Verification Before Completion

### Original Bug Check
- [ ] Original bug no longer occurs

### Regression Check
- [ ] Related behavior still works
- [ ] Manual fallback still works (if applicable)
- [ ] No nearby regressions found

### Verification Evidence
*List exact commands run, test cases, UI flows checked, logs, or screenshots.*

---

## 9. Completion

### Summary
- Root cause:
- Files changed:
- Tests run:
- Verification results:
- Remaining risks:

### Approval
- [ ] Approved for commit
- [ ] Approved for push

---

## 10. Commit Description

*Draft the commit message here.*

\`\`\`
[commit message]
\`\`\`

---

## 11. Change Log

- [Date/Time] Created debug spec
- [Date/Time] Reproduction documented
- [Date/Time] Root cause confirmed
- [Date/Time] Fix approved
- [Date/Time] Verification completed