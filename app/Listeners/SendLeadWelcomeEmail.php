<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendLeadWelcomeEmail
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
    public function handle(LeadAssigned $event): void
    {
        if (isEmailTemplateEnabled('Lead Welcome', createdBy()) && $event->lead->email) {
            $lead = $event->lead;

            // Prepare email variables
            $variables = [
                '{lead_name}' => $lead->name ?? '-',
                '{lead_email}' => $lead->email ?? '-',
                '{lead_phone}' => $lead->phone ?? '-',
                '{lead_company}' => $lead->company ?? '-',
                '{company_name}' => getCompanyName(),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send welcome email to the lead's email address
                $createdByUser = User::find(createdBy());
                $userLanguage = $createdByUser->lang ?? 'en';
                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Lead Welcome',
                    variables: $variables,
                    toEmail: $lead->email,
                    toName: $lead->name,
                    language: $userLanguage
                );

            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if (
                    !str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')
                ) {
                    session()->flash('email_error', 'Failed to send lead welcome email: ' . $errorMessage);
                }
            }
        }
    }
}
