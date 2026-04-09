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
