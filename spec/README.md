# Specs Driven Development (SDD)

This directory contains task specifications used to guide development work in a controlled, reviewable way.

Every major task must begin with a spec file created from the appropriate template before implementation starts.

## Core Principle

No implementation should begin until the task is clearly defined, reviewed, and approved.

For feature work:
- spec first
- task breakdown second
- implementation third
- verification before completion

For debugging work:
- reproduce first
- trace second
- prove root cause third
- fix only after approval
- verify before completion

---

## Workflows

## 1) Feature / Build Workflow

Use this workflow for:
- new features
- UI improvements
- refactors with planned behavior changes
- integrations
- architecture or system changes

Phases:

1. **Planning**
   - Define the problem, goal, requirements, acceptance criteria, technical approach, and affected files.
   - Identify edge cases, risks, and dependencies.

2. **Verifying Specs**
   - The USER must review and approve the specification before any coding starts.
   - If requirements change, return to Planning.

3. **Verifying Technology Used**
   - Evaluate any new tools, libraries, services, or frameworks.
   - Document why they are needed and whether they fit the project.

4. **Task Breakdown**
   - Break the work into small, reviewable tasks.
   - Each task should identify:
     - the files/modules involved
     - expected change
     - tests to add/update
     - verification steps

5. **Implementation**
   - Implement one task at a time.
   - Keep changes minimal and scoped.
   - Avoid unrelated refactors.
   - If the approved spec is no longer correct, stop and return to Planning.

6. **Task Review Gate**
   - After each task, review:
     - spec compliance
     - code quality
     - unintended changes
     - test coverage

7. **QA / Verification**
   - Verify the finished work against the approved spec and acceptance criteria.
   - Use automated tests, end-to-end tests, browser testing, or manual validation as appropriate.
   - Confirm edge cases and regression risks are covered.

8. **Completion**
   - Summarize:
     - files changed
     - tests run
     - results
     - remaining risks
   - Commit only after explicit approval.
   - Push only after explicit approval.

---

## 2) Debugging Workflow

Use this workflow for:
- bugs
- regressions
- broken integrations
- incorrect runtime behavior
- async/realtime issues
- production or staging defects

Phases:

1. **Reproduction**
   - Reproduce the issue reliably.
   - Document exact steps, expected behavior, and actual behavior.

2. **Trace the Flow**
   - Trace the relevant system end-to-end.
   - Review only the files/modules/services involved.
   - Compare the broken path against a working or expected path when possible.

3. **Hypotheses**
   - Form possible explanations for the bug.
   - Test one hypothesis at a time.
   - Do not stack random fixes.

4. **Root Cause**
   - Identify and document the exact root cause with evidence.
   - No fix should be implemented until the root cause is proven.

5. **Fix Plan Approval**
   - Propose the smallest safe fix.
   - Wait for approval before changing production code.

6. **Minimal Fix**
   - Implement only the approved fix.
   - Avoid unrelated refactors.

7. **Verification Before Completion**
   - Prove the original bug is fixed.
   - Prove nearby behavior still works.
   - Add or update regression tests where appropriate.

8. **Completion**
   - Summarize:
     - root cause
     - files changed
     - tests run
     - verification results
     - remaining risks
   - Commit only after explicit approval.
   - Push only after explicit approval.

---

## Rules

- Keep specs atomic: one major task or one cohesive debugging effort per spec.
- Do not start coding before spec approval.
- Do not silently expand scope.
- If the task changes materially, return to the Planning or Root Cause stage.
- Do not commit or push automatically.
- Every spec must include the current date and time of creation.
- Every completed task must include verification evidence, not just a claim that it works.

---

## Recommended Files

- `_feature_template.md` for feature/build work
- `_debug_template.md` for debugging work

Use the correct template depending on the task type.