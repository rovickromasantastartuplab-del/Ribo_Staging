<?php

namespace App\Listeners;

use App\Events\CaseCreated;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendCaseCreatedEmail
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
    public function handle(CaseCreated $event): void
    {
        $case = $event->case;
        $assignedUser = $case->assignedUser;
        $contact = $case->contact;

        if (isEmailTemplateEnabled('Case Created', createdBy()) && $case->assigned_to) {
            // Prepare email variables
            $variables = [
                '{contact_name}' => $contact->name ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{case_subject}' => $case->subject ?? '-',
                '{case_priority}' => $case->priority ?? '-',
                '{case_status}' => $case->status ?? '-',
                '{case_created_date}' => $case->created_at ?? '-',
                '{case_description}' => $case->description ?? '-',
                '{company_name}' => getCompanyName(),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');
                // Send case created email to the assigned user
                $createdByUser = User::find(createdBy());
                $userLanguage = $createdByUser->lang ?? 'en';
                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Case Created',
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
                    session()->flash('email_error', 'Failed to send case create email: ' . $errorMessage);
                }
            }
        }
    }
}
