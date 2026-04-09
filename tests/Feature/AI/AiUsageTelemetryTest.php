<?php

use App\Models\User;
use App\Models\AiUsageLog;
use App\Services\AI\AiUsageCostCalculator;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('calculates ai usage costs correctly', function () {
    $calculator = new AiUsageCostCalculator();

    // gpt-4o-mini: $0.15 / 1M input, $0.60 / 1M output
    // 1M tokens = $0.15
    // 1 token = $0.00000015
    // 10,000 tokens = $0.0015
    
    // Testing gpt-4o-mini
    $cost = $calculator->calculate('gpt-4o-mini', 10000, 10000);
    expect($cost)->toBe(0.0075); // (10000 * 0.00000015) + (10000 * 0.00000060) = 0.0015 + 0.006 = 0.0075
});

it('includes ai usage insights in superadmin dashboard data', function () {
    $superadmin = User::factory()->create(['type' => 'superadmin']);
    $company = User::factory()->create(['type' => 'company']);
    
    // Create some logs
    AiUsageLog::create([
        'created_by' => $company->id,
        'feature' => 'triage',
        'model_version' => 'gpt-4o-mini',
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'requested_at' => now(),
    ]);

    actingAs($superadmin);

    $response = get(route('dashboard'));
    $response->assertStatus(200);
    
    $dashboardData = $response->viewData('page')['props']['dashboardData'];
    
    expect($dashboardData)->toHaveKey('aiUsage');
    expect($dashboardData['aiUsage']['stats']['totalTokens'])->toBe(150);
});
