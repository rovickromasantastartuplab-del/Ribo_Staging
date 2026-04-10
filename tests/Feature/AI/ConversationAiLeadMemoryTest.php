<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Models\AiMemorySummary;
use App\Models\AiTask;
use App\Models\Lead;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function createLeadMemoryFixture(): array
{
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    updateSetting('ai_conversation_enabled', '1', $superadmin->id);
    updateSetting('ai_conversation_api_key', 'test-key', $superadmin->id);
    
    $company = User::factory()->create([
        'type' => 'company',
        'created_by' => $superadmin->id,
        'email_verified_at' => now(),
    ]);

    $staff = User::factory()->create([
        'type' => 'staff',
        'created_by' => $company->id,
        'email_verified_at' => now(),
    ]);

    $lead = Lead::query()->create([
        'name' => 'John Lead',
        'email' => 'john@lead.test',
        'created_by' => $company->id,
        'status' => 'active',
    ]);

    return [$staff, $company, $lead];
}

function disableMiddlewareForLeadMemory(): void
{
    test()->withoutMiddleware([
        CheckInstallation::class,
        CheckPlanAccess::class,
        EnsureOnboardingCompleted::class,
    ]);
}

it('returns memory summary and tasks for a lead', function () {
    [$staff, $company, $lead] = createLeadMemoryFixture();
    actingAs($staff);
    disableMiddlewareForLeadMemory();

    AiMemorySummary::query()->create([
        'created_by' => $company->id,
        'lead_id' => $lead->id,
        'relationship_summary' => 'Promising new lead with specific budget constraints.',
        'relationship_strength' => 'moderate',
        'summarized_at' => now(),
    ]);

    AiTask::query()->create([
        'created_by' => $company->id,
        'lead_id' => $lead->id,
        'title' => 'Follow up on budget',
        'priority' => 'medium',
        'is_completed' => false,
    ]);

    // We expect the API to handle the entity_type parameter
    getJson("/ai/memory/{$lead->id}?entity_type=lead")
        ->assertOk()
        ->assertJsonPath('data.lead_id', $lead->id)
        ->assertJsonPath('data.relationship_summary', 'Promising new lead with specific budget constraints.')
        ->assertJsonFragment(['title' => 'Follow up on budget']);
});
