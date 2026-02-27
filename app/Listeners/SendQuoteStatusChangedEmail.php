<?php

namespace App\Listeners;

use App\Events\QuoteStatusChanged;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\WebhookService;
use Exception;

class SendQuoteStatusChangedEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private EmailTemplateService $emailService,
        private WebhookService $webhookService
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(QuoteStatusChanged $event): void
    {
        $quote = $event->quote;
        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;
        $billingContact = $quote->billingContact;
        $assignedUser = $quote->assignedUser;
        $account = $quote->account;

        if (isEmailTemplateEnabled('Quote Status Changed', createdBy())) {
            // Prepare email variables
            $variables = [
                '{quote_number}' => $quote->quote_number ?? '-',
                '{quote_name}' => $quote->name ?? '-',
                '{billing_contact_name}' => $billingContact->name ?? '-',
                '{account_name}' => $account->name ?? '-',
                '{quote_total}' => $quote->total_amount ? formatCurrency((float) $quote->total_amount, createdBy()) : formatCurrency(0, createdBy()),
                '{quote_valid_until}' => $quote->valid_until ? date('Y-m-d', strtotime($quote->valid_until)) : '-',
                '{old_quote_status}' => ucfirst($oldStatus),
                '{new_quote_status}' => ucfirst($newStatus),
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_user_email}' => $assignedUser->email ?? '-',
                '{company_name}' => getCompanyName(),
                '{view_link}' => route('quotes.public', ['quote' => encrypt($quote->id)]),
            ];

            try {
                // Generate Quote PDF Attachment
                $pdfPath = storage_path('app/temp_quote_status_' . $quote->id . '_' . time() . '.pdf');

                // Pre-load relationships that the PDF requires to avoid N+1 and null issues in the view
                $quote->loadMissing(['products.tax', 'account', 'billingContact']);

                \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quote', compact('quote'))->save($pdfPath);

                // Send email to billing contact if exists
                if ($billingContact && $billingContact->email) {
                    $createdByUser = User::find(createdBy());
                    $userLanguage = $createdByUser->lang ?? 'en';
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Quote Status Changed',
                        variables: $variables,
                        toEmail: $billingContact->email,
                        toName: $billingContact->name,
                        language: $userLanguage,
                        attachmentPath: $pdfPath
                    );
                }

                // Send email to assigned user if exists and different from billing contact
                if (
                    $assignedUser && $assignedUser->email &&
                    (!$billingContact || $assignedUser->email !== $billingContact->email)
                ) {
                    $createdByUser = User::find(createdBy());
                    $userLanguage = $createdByUser->lang ?? 'en';
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Quote Status Changed',
                        variables: $variables,
                        toEmail: $assignedUser->email,
                        toName: $assignedUser->name,
                        language: $userLanguage,
                        attachmentPath: $pdfPath
                    );
                }

                // Trigger webhooks for Quote Status Changed
                if ($assignedUser && $assignedUser->id) {
                    $this->webhookService->triggerWebhooks('Quote Status Changed', $quote->toArray(), $quote->created_by ?? $quote->id);
                }

                // Clean up the temporary PDF file
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            } catch (Exception $e) {
                // Store error in session for frontend notification
                session()->flash('email_error', 'Failed to send quote status changed email: ' . $e->getMessage());
            }
        }
    }
}
