# Conversation AI Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-ready backend/database foundation for Conversation AI features (triage, memory, drafting, reports, feedback) using superadmin-managed AI credentials and phased rollout.

**Architecture:** Use Laravel `Controller -> Service -> Provider/Prompt/Rules -> Repository(Model)` flow, keep routes in `routes/web.php`, keep configuration in existing `settings` table, and add new AI domain tables scoped by tenant (`created_by`) for conversation workflows.

**Tech Stack:** Laravel 12, Eloquent, Queue Jobs, OpenAI PHP client, MySQL migrations, Pest feature tests.

---

### Task 1: Establish AI Backend Skeleton (No Feature Endpoints Yet)

**Files:**
- Create: `app/Services/AI/ConversationAiConfigService.php`
- Create: `app/Services/AI/Providers/OpenAiConversationClient.php`
- Create: `app/Services/AI/Rules/ConversationAiRules.php`
- Create: `app/Services/AI/Prompts/BasePromptFactory.php`
- Create: `app/Services/AI/ConversationAiTelemetryService.php`
- Test: `tests/Feature/AI/ConversationAiConfigServiceTest.php`

- [ ] **Step 1: Create failing config-service tests**

```php
<?php

use App\Models\User;
use function Pest\Laravel\actingAs;

it('resolves ai config from superadmin settings', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    actingAs($superadmin);

    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', 'test-key', $superadmin->id);
    updateSetting('ai_conversation_model', 'gpt-5.4-mini', $superadmin->id);

    $service = app(\App\Services\AI\ConversationAiConfigService::class);
    $config = $service->resolve();

    expect($config['enabled'])->toBeTrue();
    expect($config['api_key'])->toBe('test-key');
    expect($config['model'])->toBe('gpt-5.4-mini');
});
```

- [ ] **Step 2: Run test and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiConfigServiceTest.php`  
Expected: FAIL with missing classes.

- [ ] **Step 3: Implement minimal AI config/provider/rules scaffolding**

```php
<?php

namespace App\Services\AI;

class ConversationAiConfigService
{
    public function resolve(): array
    {
        $superadmin = \App\Models\User::where('type', 'superadmin')->first();
        $superadminId = $superadmin?->id;

        return [
            'enabled' => getSetting('ai_conversation_enabled', '0', $superadminId) === '1',
            'api_key' => (string) getSetting('ai_conversation_api_key', '', $superadminId),
            'model' => (string) getSetting('ai_conversation_model', 'gpt-5.4-mini', $superadminId),
            'timeout_seconds' => (int) getSetting('ai_conversation_timeout_seconds', 30, $superadminId),
        ];
    }
}
```

- [ ] **Step 4: Re-run tests for green baseline**

Run: `php artisan test tests/Feature/AI/ConversationAiConfigServiceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI tests/Feature/AI/ConversationAiConfigServiceTest.php
git commit -m "feat(ai): add conversation ai foundation services and config resolution"
```

### Task 2: Add Superadmin Settings Contract for Conversation AI

**Files:**
- Modify: `app/Http/Controllers/Settings/SystemSettingsController.php`
- Modify: `app/Helpers/helper.php`
- Modify: `resources/js/pages/settings/system-settings.tsx` (or matching existing settings page file)
- Test: `tests/Feature/Settings/ConversationAiSettingsTest.php`

- [ ] **Step 1: Write failing settings tests**

```php
<?php

use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('allows superadmin to save conversation ai settings', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    actingAs($superadmin);

    post(route('settings.system.update-chatgpt'), [
        'chatgptKey' => 'legacy-key',
        'chatgptModel' => 'gpt-4o-mini',
        'ai_conversation_enabled' => true,
        'ai_conversation_api_key' => 'new-key',
        'ai_conversation_model' => 'gpt-5.4-mini',
    ])->assertSessionHasNoErrors();
});
```

- [ ] **Step 2: Run test and verify failure**

Run: `php artisan test tests/Feature/Settings/ConversationAiSettingsTest.php`  
Expected: FAIL due to missing validation/fields.

- [ ] **Step 3: Implement settings validation and persistence**

```php
// Add to SystemSettingsController
$validated = $request->validate([
    'ai_conversation_enabled' => 'boolean',
    'ai_conversation_api_key' => 'nullable|string',
    'ai_conversation_model' => 'nullable|string',
    'ai_conversation_timeout_seconds' => 'nullable|integer|min:5|max:120',
]);

updateSetting('ai_conversation_enabled', ($validated['ai_conversation_enabled'] ?? false) ? '1' : '0');
updateSetting('ai_conversation_api_key', $validated['ai_conversation_api_key'] ?? '');
updateSetting('ai_conversation_model', $validated['ai_conversation_model'] ?? 'gpt-5.4-mini');
updateSetting('ai_conversation_timeout_seconds', (string)($validated['ai_conversation_timeout_seconds'] ?? 30));
```

- [ ] **Step 4: Re-run settings tests**

Run: `php artisan test tests/Feature/Settings/ConversationAiSettingsTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Settings/SystemSettingsController.php app/Helpers/helper.php resources/js/pages/settings
git add tests/Feature/Settings/ConversationAiSettingsTest.php
git commit -m "feat(settings): add superadmin conversation ai configuration keys"
```

### Task 3: Create Conversation AI Database Schema (Tenant-Scoped)

**Files:**
- Create: `database/migrations/2026_04_09_000001_create_ai_triage_results_table.php`
- Create: `database/migrations/2026_04_09_000002_create_ai_memory_summaries_table.php`
- Create: `database/migrations/2026_04_09_000003_create_ai_tasks_table.php`
- Create: `database/migrations/2026_04_09_000004_create_ai_draft_runs_table.php`
- Create: `database/migrations/2026_04_09_000005_create_ai_report_jobs_table.php`
- Create: `database/migrations/2026_04_09_000006_create_ai_feedback_logs_table.php`
- Create: `database/migrations/2026_04_09_000007_create_ai_usage_logs_table.php`
- Create: `app/Models/AiTriageResult.php`
- Create: `app/Models/AiMemorySummary.php`
- Create: `app/Models/AiTask.php`
- Create: `app/Models/AiDraftRun.php`
- Create: `app/Models/AiReportJob.php`
- Create: `app/Models/AiFeedbackLog.php`
- Create: `app/Models/AiUsageLog.php`
- Test: `tests/Feature/AI/ConversationAiSchemaTest.php`

- [ ] **Step 1: Write failing migration/model tests**

```php
<?php

it('has conversation ai tables', function () {
    expect(\Schema::hasTable('ai_triage_results'))->toBeTrue();
    expect(\Schema::hasTable('ai_memory_summaries'))->toBeTrue();
    expect(\Schema::hasTable('ai_tasks'))->toBeTrue();
    expect(\Schema::hasTable('ai_draft_runs'))->toBeTrue();
    expect(\Schema::hasTable('ai_report_jobs'))->toBeTrue();
    expect(\Schema::hasTable('ai_feedback_logs'))->toBeTrue();
    expect(\Schema::hasTable('ai_usage_logs'))->toBeTrue();
});
```

- [ ] **Step 2: Run targeted test and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiSchemaTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement migrations with tenant indexes and foreign keys**

```php
// Example triage table shape
$table->id();
$table->unsignedBigInteger('created_by')->index();
$table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
$table->string('intent', 50)->nullable();
$table->unsignedTinyInteger('intent_confidence')->nullable();
$table->string('priority', 20)->nullable();
$table->unsignedTinyInteger('success_probability')->nullable();
$table->string('behavioral_pulse', 30)->nullable();
$table->text('summary')->nullable();
$table->json('strategic_action_json')->nullable();
$table->string('model_version')->nullable();
$table->string('prompt_version')->nullable();
$table->timestamp('analyzed_at')->nullable();
$table->timestamps();
$table->unique(['created_by', 'email_thread_id']);
```

- [ ] **Step 4: Run migrations + tests**

Run: `php artisan migrate`  
Run: `php artisan test tests/Feature/AI/ConversationAiSchemaTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models tests/Feature/AI/ConversationAiSchemaTest.php
git commit -m "feat(ai): add conversation ai persistence schema and models"
```

### Task 4: Phase 1 Endpoints (Triage)

**Files:**
- Create: `app/Http/Controllers/AI/ConversationAiTriageController.php`
- Create: `app/Services/AI/Prompts/TriagePromptFactory.php`
- Create: `app/Services/AI/Skills/TriageSkill.php`
- Create: `app/Services/AI/ConversationAiTriageService.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiTriageApiTest.php`

- [ ] **Step 1: Write failing triage endpoint tests**

```php
<?php

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('returns triage payload for thread', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    getJson("/ai/triage/{$thread->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['intent', 'priority', 'summary']]);
});

it('refreshes triage for thread', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    postJson("/ai/triage/{$thread->id}/refresh")
        ->assertOk()
        ->assertJsonStructure(['data' => ['email_thread_id', 'analyzed_at']]);
});
```

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiTriageApiTest.php`  
Expected: FAIL (missing routes/controllers).

- [ ] **Step 3: Implement routes and minimal service/controller flow**

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ai/triage/{thread}', [ConversationAiTriageController::class, 'show']);
    Route::post('/ai/triage/{thread}/refresh', [ConversationAiTriageController::class, 'refresh']);
});
```

- [ ] **Step 4: Re-run tests**

Run: `php artisan test tests/Feature/AI/ConversationAiTriageApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI app/Services/AI routes/web.php tests/Feature/AI/ConversationAiTriageApiTest.php
git commit -m "feat(ai): add conversation triage endpoints and service pipeline"
```

### Task 5: Phase 2 Endpoints (Memory + Tasks)

**Files:**
- Create: `app/Http/Controllers/AI/ConversationAiMemoryController.php`
- Create: `app/Http/Controllers/AI/ConversationAiTasksController.php`
- Create: `app/Services/AI/Prompts/MemoryPromptFactory.php`
- Create: `app/Services/AI/Skills/MemorySkill.php`
- Create: `app/Services/AI/ConversationAiMemoryService.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiMemoryApiTest.php`

- [ ] **Step 1: Write failing memory/tasks tests**

```php
<?php

it('returns memory summary and tasks for contact', function () {
    $this->actingAs($this->companyUser());
    $contact = $this->contactForCompany();
    $this->getJson("/ai/memory/{$contact->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['relationship_summary', 'tasks']]);
});

it('updates task completion state', function () {
    $this->actingAs($this->companyUser());
    $task = $this->taskForCompany();
    $this->patchJson("/ai/tasks/{$task->id}", ['is_completed' => true])
        ->assertOk();
});
```

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiMemoryApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement memory/task controllers and services**

```php
Route::get('/ai/memory/{contact}', [ConversationAiMemoryController::class, 'show']);
Route::patch('/ai/tasks/{task}', [ConversationAiTasksController::class, 'update']);
```

- [ ] **Step 4: Re-run tests**

Run: `php artisan test tests/Feature/AI/ConversationAiMemoryApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI app/Services/AI routes/web.php tests/Feature/AI/ConversationAiMemoryApiTest.php
git commit -m "feat(ai): add conversation memory and tasks endpoints"
```

### Task 6: Phase 3 Endpoint (Drafting)

**Files:**
- Create: `app/Http/Controllers/AI/ConversationAiDraftController.php`
- Create: `app/Services/AI/Prompts/DraftPromptFactory.php`
- Create: `app/Services/AI/Skills/DraftSkill.php`
- Create: `app/Services/AI/ConversationAiDraftService.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiDraftApiTest.php`

- [ ] **Step 1: Write failing draft endpoint test**

```php
<?php

it('generates ai draft from prompt and tone', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    $this->postJson('/ai/draft', [
        'threadId' => $thread->id,
        'prompt' => 'Write a professional follow-up',
        'tone' => 'professional',
    ])->assertOk()->assertJsonStructure(['data' => ['subject', 'body']]);
});
```

- [ ] **Step 2: Run test and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiDraftApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement draft route/controller/service + draft run logging**

```php
Route::post('/ai/draft', [ConversationAiDraftController::class, 'store']);
```

- [ ] **Step 4: Re-run tests**

Run: `php artisan test tests/Feature/AI/ConversationAiDraftApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI app/Services/AI routes/web.php tests/Feature/AI/ConversationAiDraftApiTest.php
git commit -m "feat(ai): add conversation drafting endpoint and run tracking"
```

### Task 7: Phase 4 Endpoints/Jobs (Reports)

**Files:**
- Create: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Create: `app/Services/AI/Prompts/ReportPromptFactory.php`
- Create: `app/Services/AI/Skills/ReportSkill.php`
- Create: `app/Services/AI/ConversationAiReportService.php`
- Create: `app/Jobs/AI/GenerateConversationAiReportJob.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiReportApiTest.php`

- [ ] **Step 1: Write failing report tests**

```php
<?php

it('creates report job and returns processing status', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    $this->postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->assertJsonStructure(['data' => ['job_id', 'status']]);
});
```

- [ ] **Step 2: Run test and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiReportApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement async report job flow**

```php
Route::post('/ai/reports/generate', [ConversationAiReportController::class, 'generate']);
Route::get('/ai/reports/{job}', [ConversationAiReportController::class, 'show']);
```

- [ ] **Step 4: Re-run tests**

Run: `php artisan test tests/Feature/AI/ConversationAiReportApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI app/Services/AI app/Jobs/AI routes/web.php tests/Feature/AI/ConversationAiReportApiTest.php
git commit -m "feat(ai): add async conversation ai report generation pipeline"
```

### Task 8: Phase 5 Endpoint (Feedback + Overrides)

**Files:**
- Create: `app/Http/Controllers/AI/ConversationAiFeedbackController.php`
- Create: `app/Services/AI/ConversationAiFeedbackService.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiFeedbackApiTest.php`

- [ ] **Step 1: Write failing feedback tests**

```php
<?php

it('stores ai feedback event', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    $this->postJson('/ai/feedback', [
        'threadId' => $thread->id,
        'error_type' => 'misclassification',
        'notes' => 'Priority should be medium, not urgent',
    ])->assertOk();
});
```

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiFeedbackApiTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement feedback endpoint + persistence**

```php
Route::post('/ai/feedback', [ConversationAiFeedbackController::class, 'store']);
```

- [ ] **Step 4: Re-run tests**

Run: `php artisan test tests/Feature/AI/ConversationAiFeedbackApiTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI app/Services/AI routes/web.php tests/Feature/AI/ConversationAiFeedbackApiTest.php
git commit -m "feat(ai): add conversation ai feedback endpoint and logs"
```

### Task 9: Phase 6 Hardening (Rules, Limits, Observability, Regression Suite)

**Files:**
- Modify: `app/Services/AI/Rules/ConversationAiRules.php`
- Modify: `app/Services/AI/Providers/OpenAiConversationClient.php`
- Modify: `app/Http/Controllers/AI/*.php`
- Modify: `app/Services/AI/ConversationAiTelemetryService.php`
- Create: `tests/Feature/AI/ConversationAiAuthorizationTest.php`
- Create: `tests/Feature/AI/ConversationAiRateLimitTest.php`
- Create: `tests/Feature/AI/ConversationAiFailureFallbackTest.php`

- [ ] **Step 1: Add failing authorization and fallback tests**

```php
<?php

it('forbids cross-company thread access on ai endpoints', function () {
    $this->actingAs($this->companyUserA());
    $threadOfOtherCompany = $this->threadForCompanyB();
    $this->getJson("/ai/triage/{$threadOfOtherCompany->id}")->assertForbidden();
});
```

- [ ] **Step 2: Run test suite and verify failures**

Run: `php artisan test tests/Feature/AI`  
Expected: FAIL in auth/rate/fallback cases.

- [ ] **Step 3: Implement hardening rules**

```php
// Enforce created_by ownership in all AI controllers
abort_if($entity->created_by !== auth()->user()->creatorId(), 403);

// Enforce AI-enabled + API key present
if (!$config['enabled'] || empty($config['api_key'])) {
    return response()->json(['message' => 'AI unavailable'], 422);
}
```

- [ ] **Step 4: Run full AI test suite and queue smoke checks**

Run: `php artisan test tests/Feature/AI`  
Run: `php artisan test tests/Feature/Settings/ConversationAiSettingsTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI app/Http/Controllers/AI tests/Feature/AI tests/Feature/Settings/ConversationAiSettingsTest.php
git commit -m "chore(ai): harden conversation ai auth rules and fallback behavior"
```

### Task 10: Integration with Existing Frontend AI Components

**Files:**
- Modify: `resources/js/pages/conversations/components/ConversationAiPanel.tsx`
- Modify: `resources/js/pages/conversations/components/AiTriageCard.tsx`
- Modify: `resources/js/pages/conversations/components/AiMemoryCard.tsx`
- Modify: `resources/js/pages/conversations/components/AiReplyAssistantCard.tsx`
- Modify: `resources/js/pages/conversations/components/EditorAiAssistant.tsx`
- Modify: `resources/js/pages/conversations/utils/mockAiData.ts`
- Test: `tests/Feature/AI/ConversationAiContractsTest.php`

- [ ] **Step 1: Write failing contract tests for frontend API payload shape**

```php
<?php

it('returns stable triage contract for conversation ui', function () {
    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();

    $this->getJson("/ai/triage/{$thread->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary', 'intent', 'intent_confidence', 'priority',
                'suggested_status', 'success_probability', 'behavioral_pulse',
                'strategic_action' => ['goal', 'reason', 'recommendation'],
            ],
        ]);
});
```

- [ ] **Step 2: Run tests and verify failures**

Run: `php artisan test tests/Feature/AI/ConversationAiContractsTest.php`  
Expected: FAIL if payloads drift.

- [ ] **Step 3: Align frontend from mock data to backend endpoints**

```ts
// Replace getMockTriage/getMockMemory with axios calls:
// GET /ai/triage/{threadId}
// GET /ai/memory/{contactId}
// POST /ai/draft
// POST /ai/reports/generate
// PATCH /ai/tasks/{taskId}
```

- [ ] **Step 4: Re-run contracts + frontend tests**

Run: `php artisan test tests/Feature/AI/ConversationAiContractsTest.php`  
Run: `npm run test:unit -- --run`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/conversations tests/Feature/AI/ConversationAiContractsTest.php
git commit -m "feat(conversations): wire ai sidebar and reply assistant to backend contracts"
```

### Task 11: Release Readiness and Rollout Controls

**Files:**
- Create: `docs/ai/conversation-ai-operational-runbook.md`
- Create: `docs/ai/conversation-ai-api-contracts.md`
- Modify: `app/Services/AI/ConversationAiConfigService.php`
- Test: `tests/Feature/AI/ConversationAiRolloutTest.php`

- [ ] **Step 1: Add failing rollout test for feature toggle behavior**

```php
<?php

it('returns 422 when conversation ai is disabled by superadmin', function () {
    $superadmin = $this->superadminUser();
    updateSetting('ai_conversation_enabled', '0', $superadmin->id);

    $this->actingAs($this->companyUser());
    $thread = $this->threadForCompany();
    $this->postJson("/ai/triage/{$thread->id}/refresh")->assertStatus(422);
});
```

- [ ] **Step 2: Run test and verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiRolloutTest.php`  
Expected: FAIL.

- [ ] **Step 3: Implement rollout safeguards and docs**

```md
Runbook includes:
- Required settings keys
- Queue worker requirements
- Failure fallback matrix
- Incident checklist
```

- [ ] **Step 4: Final verification pass**

Run: `php artisan test tests/Feature/AI tests/Feature/Settings/ConversationAiSettingsTest.php`  
Run: `php artisan migrate:status`  
Expected: PASS and new migrations applied.

- [ ] **Step 5: Commit**

```bash
git add docs/ai app/Services/AI tests/Feature/AI/ConversationAiRolloutTest.php
git commit -m "docs(ai): add rollout runbook and finalize conversation ai release guards"
```

## Phase Summary (Delivery Sequence)

1. Phase 0: AI foundation services and superadmin config contract.
2. Phase 1: Triage API and refresh pipeline.
3. Phase 2: Memory + tasks APIs.
4. Phase 3: Draft generation API.
5. Phase 4: Report generation async API + job.
6. Phase 5: Feedback logging API.
7. Phase 6: Hardening, contracts, observability, rollout controls.

## Definition of Done

1. All AI conversation endpoints are available behind authenticated routes in `routes/web.php`.
2. All new AI tables are tenant-scoped and indexed for company-level queries.
3. Superadmin-only AI token/model settings are used globally for all company/staff requests.
4. Frontend conversation AI components no longer rely on mock data for production flow.
5. Feature tests for AI endpoints, auth boundaries, and fallback behavior pass.
6. Operational runbook and API contract docs are added for maintenance handoff.
