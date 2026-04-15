# Conversation AI API Contracts

## Overview
Conversation AI endpoints are authenticated and tenant-scoped. Cross-company access is forbidden or not found depending on endpoint entity resolution rules.

Unavailable AI contract:
- Status: `422`
- Body: `{"message":"AI unavailable"}`

This applies when:
- Conversation AI is disabled by superadmin.
- API key is missing/blank.
- Provider execution fails under guarded controllers.

## Endpoints

### `GET /ai/triage/{threadId}`
Response `200`:
```json
{
  "data": {
    "id": 1,
    "email_thread_id": 10,
    "intent": "follow_up",
    "intent_confidence": 68,
    "priority": "medium",
    "thread_state": "active",
    "relationship_health": "neutral",
    "actionability": "act_now",
    "suggested_status": "Open",
    "success_probability": 55,
    "behavioral_pulse": "stable",
    "summary": "Thread requires attention: Subject.",
    "strategic_action": {
      "goal": "advance_conversation",
      "reason": "customer_activity_detected",
      "recommendation": "Leads: Send a concise follow-up response"
    },
    "analyzed_at": "2026-04-09T12:00:00+00:00"
  }
}
```
Errors:
- `403` cross-company thread access.
- `422` AI unavailable.

### `POST /ai/triage/{threadId}/refresh`
Response `200`: same payload shape as triage show.
Errors:
- `403` cross-company thread access.
- `422` AI unavailable.

### `GET /ai/memory/{contactId}`
Response `200`:
```json
{
  "data": {
    "contact_id": 5,
    "relationship_summary": "Recent interactions...",
    "relationship_strength": "moderate",
    "memory_points": ["follow_up_requested"],
    "tasks": [
      {
        "id": 99,
        "title": "Send follow-up",
        "description": null,
        "priority": "high",
        "is_completed": false,
        "due_at": null,
        "completed_at": null
      }
    ]
  }
}
```
Errors:
- `403` cross-company contact access.
- `422` AI unavailable.

### `PATCH /ai/tasks/{taskId}`
Request:
```json
{
  "is_completed": true
}
```
Response `200`:
```json
{
  "data": {
    "id": 99,
    "is_completed": true,
    "completed_at": "2026-04-09T12:00:00+00:00"
  }
}
```
Errors:
- `403` cross-company task access.
- `422` AI unavailable.

### `POST /ai/draft`
Request:
```json
{
  "threadId": 10,
  "prompt": "Write a professional follow-up",
  "tone": "professional"
}
```
Response `200`:
```json
{
  "data": {
    "id": 15,
    "subject": "Re: ...",
    "body": "<p>...</p>",
    "generated_at": "2026-04-09T12:00:00+00:00"
  }
}
```
Errors:
- `404` thread not found for tenant.
- `422` AI unavailable.

### `POST /ai/reports/generate`
Request:
```json
{
  "threadId": 10,
  "scope": "overall",
  "contactId": null
}
```
Response `200`:
```json
{
  "data": {
    "job_id": 200,
    "status": "queued"
  }
}
```
Errors:
- `404` thread/contact not found for tenant.
- `422` AI unavailable.

### `GET /ai/reports/{jobId}`
Response `200`:
```json
{
  "data": {
    "id": 200,
    "status": "queued",
    "scope": "overall",
    "result": null,
    "error_message": null,
    "requested_at": "2026-04-09T12:00:00+00:00",
    "completed_at": null
  }
}
```
Errors:
- `403` cross-company report job access.

### `POST /ai/feedback`
Request:
```json
{
  "threadId": 10,
  "triageResultId": 1,
  "contactId": 5,
  "taskId": 99,
  "error_type": "misclassification",
  "notes": "Priority should be medium",
  "payload": {}
}
```
Response `200`:
```json
{
  "data": {
    "id": 300,
    "error_type": "misclassification",
    "logged_at": "2026-04-09T12:00:00+00:00"
  }
}
```
Errors:
- `404` referenced entities not found for tenant.
- `422` AI unavailable.

## Contract Stability Notes
- `data` envelope is stable across endpoints.
- Fallback `422` response shape is intentionally minimal and does not expose provider internals.
- Superadmin settings are the single source of truth for Conversation AI availability.
