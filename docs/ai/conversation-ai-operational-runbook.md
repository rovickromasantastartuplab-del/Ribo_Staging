# Conversation AI Operational Runbook

## Purpose
This runbook covers production operations for Conversation AI features (triage, memory, drafting, reports, feedback, tasks) and defines release safeguards, monitoring expectations, and incident procedures.

## Required Superadmin Settings
All Conversation AI runtime configuration is resolved from superadmin-owned settings.

Required keys:
- `ai_conversation_enabled`: `1` to allow AI endpoint execution, `0` to force controlled fallback.
- `ai_conversation_api_key`: provider API key used globally by all tenants.
- `ai_conversation_model`: model identifier (default `gpt-5.4-mini`).
- `ai_conversation_timeout_seconds`: request timeout guardrail.

Operational requirements:
- Keep `ai_conversation_enabled=0` during emergency rollback.
- Treat blank/missing `ai_conversation_api_key` as unavailable state.
- Rotate API keys only through superadmin settings flows.

## Queue Worker Requirements
Conversation AI report generation uses queue jobs.

Minimum worker baseline:
- At least one active queue worker for the app queue connection.
- Worker auto-restart on deploy (`queue:restart` or process manager restart).
- Retry policy and failed-job storage configured for production.

Recommended worker controls:
- Supervisor/systemd process monitoring with auto-restart.
- Alerts on failed AI report jobs and queue backlog growth.
- Log aggregation for job failures and timeout spikes.

## Failure Fallback Matrix
When AI is unavailable (disabled, missing key, provider failure), endpoints must fail safely with:
- Status: `422`
- Body: `{"message":"AI unavailable"}`

| Endpoint | Method | Fallback Behavior |
|---|---|---|
| `/ai/triage/{threadId}` | `GET` | `422` controlled unavailable response |
| `/ai/triage/{threadId}/refresh` | `POST` | `422` controlled unavailable response |
| `/ai/memory/{contactId}` | `GET` | `422` controlled unavailable response |
| `/ai/tasks/{taskId}` | `PATCH` | `422` controlled unavailable response |
| `/ai/draft` | `POST` | `422` controlled unavailable response |
| `/ai/reports/generate` | `POST` | `422` controlled unavailable response |
| `/ai/feedback` | `POST` | `422` controlled unavailable response |

## Incident Checklist
1. Confirm blast radius:
   - Which AI endpoint(s) fail?
   - Are failures limited to one tenant or global?
2. Check rollout gates:
   - Verify `ai_conversation_enabled` and `ai_conversation_api_key` in superadmin settings.
3. Validate platform health:
   - Queue worker alive and processing.
   - Job failure volume and timeout metrics.
4. Contain:
   - If instability is global, set `ai_conversation_enabled=0` to force safe `422` fallback.
5. Recover:
   - Restore valid API key/config, restart workers if needed, and monitor error rates.
6. Verify:
   - Re-run feature tests in CI/CD (`tests/Feature/AI` and settings tests).
   - Manually check triage/draft/report flows in staging.
7. Communicate:
   - Document incident timeline, root cause, and follow-up actions.

## Release Readiness Checks
Before production rollout:
- AI feature tests pass.
- Settings contract tests pass.
- Migrations are applied and verified.
- Queue workers are healthy.
- Runbook and API contract docs are current.
