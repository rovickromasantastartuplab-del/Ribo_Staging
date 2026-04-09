<?php

use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('forbids non-superadmin from updating conversation ai settings', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    $company = User::factory()->create([
        'type' => 'company',
        'created_by' => $superadmin->id,
    ]);

    actingAs($company);
    $this->withoutMiddleware();

    post(route('settings.chatgpt.update'), [
        'chatgptKey' => 'legacy-key',
        'chatgptModel' => 'gpt-4o-mini',
        'ai_conversation_enabled' => true,
        'ai_conversation_api_key' => 'new-key',
        'ai_conversation_model' => 'gpt-5.4-mini',
        'ai_conversation_timeout_seconds' => 45,
    ])->assertForbidden();
});

it('does not expose ai conversation api key in non-superadmin resolved settings', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    $company = User::factory()->create([
        'type' => 'company',
        'created_by' => $superadmin->id,
    ]);
    $staff = User::factory()->create([
        'type' => 'staff',
        'created_by' => $company->id,
    ]);

    updateSetting('ai_conversation_api_key', 'superadmin-secret', $superadmin->id);

    expect(settings($company->id))->not->toHaveKey('ai_conversation_api_key');
    expect(settings($staff->id))->not->toHaveKey('ai_conversation_api_key');
});

it('does not expose ai conversation api key in guest/public settings resolution', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    updateSetting('ai_conversation_api_key', 'superadmin-secret', $superadmin->id);

    expect(settings())->not->toHaveKey('ai_conversation_api_key');
});
