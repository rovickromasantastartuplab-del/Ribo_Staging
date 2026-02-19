<?php

namespace App\Listeners;

use App\Events\OpportunityCreated;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendOpportunityCreatedEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OpportunityCreated $event): void
    {
        if (isEmailTemplateEnabled('Opportunity Created', createdBy()) && $event->opportunity->assigned_to) {
            $opportunity = $event->opportunity;
            $assignedUser = $opportunity->assignedUser;
            $account = $opportunity->account;
            $contact = $opportunity->contact;
            $stage = $opportunity->opportunityStage;


            // Prepare email variables
            $variables = [
                '{opportunity_name}' => $opportunity->name ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{account_name}' => $account->name ?? '-',
                '{contact_name}' => $contact->name ?? '-',
                '{opportunity_stage}' => $stage->name ?? '-',
                '{opportunity_amount}' => $opportunity->amount ?? '-',
                '{opportunity_close_date}' => $opportunity->close_date ?? '-',
                '{opportunity_description}' => $opportunity->description ?? '-',
                '{company_name}' => getCompanyName(),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send Opportunity Created email to the assigned user
                $createdByUser = User::find(createdBy());
                $userLanguage = $createdByUser->lang ?? 'en';
                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Opportunity Created',
                    variables: $variables,
                    toEmail: $assignedUser->email,
                    toName: $assignedUser->name,
                    language: $userLanguage
                );
            } catch (Exception $e) {
                // Only store error if it's not a rate limiting issue (email was likely sent successfully)
                $errorMessage = $e->getMessage();
                if (
                    !str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')
                ) {
                    session()->flash('email_error', 'Failed to send opportunity create email: ' . $errorMessage);
                }
            }
        }
    }
}
