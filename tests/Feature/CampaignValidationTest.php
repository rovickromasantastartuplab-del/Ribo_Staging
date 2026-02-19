<?php

use App\Models\Campaign;
use App\Models\CampaignType;
use App\Models\TargetList;
use App\Models\User;

use function Pest\Laravel\post;

it('validates target list is required when creating a campaign', function () {
    $user = User::factory()->create([
        'type' => 'company',
        'created_by' => null,
    ]);

    $this->actingAs($user);

    $campaignType = CampaignType::create([
        'name' => 'Type 1',
        'description' => null,
        'color' => '#3B82F6',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $targetList = TargetList::create([
        'name' => 'List 1',
        'description' => null,
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    expect(Campaign::count())->toBe(0);

    $response = post(route('campaigns.store'), [
        'name' => 'My Campaign',
        'description' => null,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'budget' => null,
        'actual_cost' => null,
        'expected_response' => null,
        'actual_response' => null,
        'campaign_type_id' => $campaignType->id,
        // 'target_list_id' intentionally omitted
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors(['target_list_id']);

    expect(Campaign::count())->toBe(0);
});
