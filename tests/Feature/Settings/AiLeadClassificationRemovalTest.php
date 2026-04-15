<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\LeadStatus;
use App\Models\Setting;
use App\Models\User;
use App\Jobs\ClassifyLeadIntentJob;
use App\Services\Omnichannel\ContactMatcherService;
use App\Services\Omnichannel\LeadEventTrackerService;
use Illuminate\Support\Facades\Queue;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('ignores removed ai lead classification settings on integrations update', function () {
    $company = User::factory()->create(['type' => 'company']);
    actingAs($company);
    $this->withoutMiddleware();

    post(route('settings.company.integrations.update'), [
        'ai_intent_enabled' => true,
        'ai_auto_apply_threshold' => 85,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('user_id', $company->id)->where('key', 'ai_intent_enabled')->doesntExist())->toBeTrue();
    expect(Setting::where('user_id', $company->id)->where('key', 'ai_auto_apply_threshold')->doesntExist())->toBeTrue();
});

it('does not queue ai lead classification when recording inbound lead events', function () {
    Queue::fake();

    $company = User::factory()->create(['type' => 'company']);
    LeadStatus::create([
        'name' => 'New',
        'color' => '#000000',
        'description' => 'Default new status',
        'status' => 'active',
        'order' => 1,
        'created_by' => $company->id,
    ]);

    $account = Account::create([
        'name' => 'Inbound Leads',
        'status' => 'active',
        'created_by' => $company->id,
    ]);

    $contact = Contact::create([
        'name' => 'Inbound Lead',
        'email' => 'lead@example.com',
        'phone' => '1234567890',
        'account_id' => $account->id,
        'created_by' => $company->id,
    ]);

    $matcher = \Mockery::mock(ContactMatcherService::class);
    $matcher->shouldReceive('matchOrCreate')
        ->once()
        ->andReturn($contact);

    $service = new LeadEventTrackerService($matcher);

    $service->recordInboundEvent([
        'channel' => 'facebook',
        'email' => 'lead@example.com',
        'phone' => '1234567890',
        'summary_text' => 'I want to learn more about pricing.',
    ], $company->id);

    Queue::assertNotPushed(ClassifyLeadIntentJob::class);
});
