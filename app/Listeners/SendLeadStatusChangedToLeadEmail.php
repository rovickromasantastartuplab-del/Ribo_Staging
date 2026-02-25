<?php

namespace App\Listeners;

use App\Events\LeadStatusChanged;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendLeadStatusChangedToLeadEmail
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
    public function handle(LeadStatusChanged $event): void
    {
        $lead = $event->lead;
        $old_status = $event->oldStatus;
        $new_status = $event->newStatus;
        $assignedUser = $event->lead->assignedUser;

        if (isEmailTemplateEnabled('Lead Status Updated', createdBy()) && $lead->email) {
            // Prepare email variables
            $variables = [
                '{lead_name}' => $lead->name ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{old_lead_stage}' => $old_status ?? '-',
                '{new_lead_stage}' => $new_status ?? '-',
                '{lead_email}' => $lead->email ?? '-',
                '{lead_phone}' => $lead->phone ?? '-',
                '{lead_company}' => $lead->company ?? '-',
                '{company_name}' => getCompanyName(),
            ];

            try {
                // Send lead status update email directly to the lead
                $createdByUser = User::find(createdBy());
                $userLanguage = $createdByUser->lang ?? 'en';

                // Clear any existing email error
                session()->forget('email_error');

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Lead Status Updated',
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
                    session()->flash('email_error', 'Failed to send lead status update email to lead: ' . $errorMessage);
                }
            }
        }
    }
}
