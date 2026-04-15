<?php

use App\Models\Setting;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('allows superadmin to save conversation ai settings', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    actingAs($superadmin);

    post(route('settings.chatgpt.update'), [
        'chatgptKey' => 'legacy-key',
        'chatgptModel' => 'gpt-4o-mini',
        'ai_conversation_enabled' => true,
        'ai_conversation_api_key' => 'new-key',
        'ai_conversation_model' => 'gpt-5.4-mini',
        'ai_conversation_timeout_seconds' => 45,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('user_id', $superadmin->id)->where('key', 'chatgptKey')->value('value'))->toBe('legacy-key');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'chatgptModel')->value('value'))->toBe('gpt-4o-mini');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_enabled')->value('value'))->toBe('1');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_api_key')->value('value'))->toBe('new-key');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_model')->value('value'))->toBe('gpt-5.4-mini');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_timeout_seconds')->value('value'))->toBe('45');
});

it('allows superadmin to save conversation ai settings without legacy chatgpt key fields', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    actingAs($superadmin);

    updateSetting('chatgptKey', 'existing-legacy-key', $superadmin->id);
    updateSetting('chatgptModel', 'gpt-4o-mini', $superadmin->id);

    post(route('settings.chatgpt.update'), [
        'ai_conversation_enabled' => true,
        'ai_conversation_api_key' => 'new-conversation-key',
        'ai_conversation_model' => 'gpt-4.1-mini',
        'ai_conversation_timeout_seconds' => 30,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('user_id', $superadmin->id)->where('key', 'chatgptKey')->value('value'))->toBe('existing-legacy-key');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'chatgptModel')->value('value'))->toBe('gpt-4o-mini');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_enabled')->value('value'))->toBe('1');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_api_key')->value('value'))->toBe('new-conversation-key');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_model')->value('value'))->toBe('gpt-4.1-mini');
    expect(Setting::where('user_id', $superadmin->id)->where('key', 'ai_conversation_timeout_seconds')->value('value'))->toBe('30');
});
