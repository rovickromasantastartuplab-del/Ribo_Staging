<?php

namespace App\Providers;

use App\Events\AccountCreate;
use App\Events\CaseCreated;
use App\Events\UserCreated;
use App\Listeners\SendUserCreatedEmail;
use App\Events\LeadAssigned;
use App\Events\LeadStatusChanged;
use App\Events\QuoteCreated;
use App\Events\QuoteStatusChanged;
use App\Events\TaskAssigned;
use App\Events\MeetingInvitation;
use App\Events\OpportunityCreated;
use App\Events\OpportunityStageChanged;
use App\Listeners\SendAssignLeadEmail;
use App\Listeners\SendCaseCreatedEmail;
use App\Listeners\SendLeadStatusChangedEmail;
use App\Listeners\SendQuoteCreatedEmail;
use App\Listeners\SendQuoteStatusChangedEmail;
use App\Listeners\SendTaskAssignedEmail;
use App\Listeners\SendMeetingInvitationEmail;
use App\Listeners\SendOpportunityCreatedEmail;
use App\Listeners\SendOpportunityStageChangedEmail;
use App\Listeners\TwilioAccountCreateListener;
use App\Listeners\TwilioCaseCreateListener;
use App\Listeners\TwilioLeadCreateListener;
use App\Listeners\TwilioMettingCreateListener;
use App\Listeners\TwilioOpportunityCreateListener;
use App\Listeners\TwilioQuoteCreateListener;
use App\Listeners\WebhookAssignLeadListener;
use App\Listeners\WebhookCaseCreateListener;
use App\Listeners\WebhookMeetingInvitationListener;
use App\Listeners\WebhookOpportunityCreateListener;
use App\Listeners\WebhookQuoteCreateListener;
use App\Listeners\WebhookTaskCreateListener;
use App\Listeners\WebhookUserCreateListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserCreated::class => [
            SendUserCreatedEmail::class,
            WebhookUserCreateListener::class,
        ],
        LeadAssigned::class => [
            SendAssignLeadEmail::class,
            TwilioLeadCreateListener::class,
            WebhookAssignLeadListener::class,
        ],
        LeadStatusChanged::class => [
            SendLeadStatusChangedEmail::class,
        ],
        QuoteCreated::class => [
            SendQuoteCreatedEmail::class,
            TwilioQuoteCreateListener::class,
            WebhookQuoteCreateListener::class,
        ],
        QuoteStatusChanged::class => [
            SendQuoteStatusChangedEmail::class,
        ],
        TaskAssigned::class => [
            SendTaskAssignedEmail::class,
            WebhookTaskCreateListener::class,
        ],
        MeetingInvitation::class => [
            SendMeetingInvitationEmail::class,
            TwilioMettingCreateListener::class,
            WebhookMeetingInvitationListener::class,
        ],
        CaseCreated::class => [
            SendCaseCreatedEmail::class,
            TwilioCaseCreateListener::class,
            WebhookCaseCreateListener::class,
        ],
        OpportunityCreated::class => [
            SendOpportunityCreatedEmail::class,
            TwilioOpportunityCreateListener::class,
            WebhookOpportunityCreateListener::class,
        ],
        OpportunityStageChanged::class => [
            SendOpportunityStageChangedEmail::class,
        ],
        AccountCreate::class => [
            TwilioAccountCreateListener::class,
        ],
        \Illuminate\Auth\Events\Registered::class => [
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
