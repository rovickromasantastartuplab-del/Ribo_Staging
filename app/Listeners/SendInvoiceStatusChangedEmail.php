<?php

namespace App\Listeners;

use App\Events\InvoiceStatusChanged;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendInvoiceStatusChangedEmail
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
    public function handle(InvoiceStatusChanged $event): void
    {
        $invoice = $event->invoice;
        $contact = $invoice->contact;
        $assignedUser = $invoice->assignedUser;
        $account = $invoice->account;

        if (isEmailTemplateEnabled('Invoice Status Changed', createdBy())) {
            // Prepare email variables
            $variables = [
                '{invoice_number}' => $invoice->invoice_number ?? '-',
                '{invoice_name}' => $invoice->name ?? '-',
                '{contact_name}' => $contact->name ?? '-',
                '{account_name}' => $account->name ?? '-',
                '{invoice_total}' => $invoice->total_amount ? formatCurrency((float) $invoice->total_amount, createdBy()) : formatCurrency(0, createdBy()),
                '{invoice_date}' => $invoice->invoice_date ? date('Y-m-d', strtotime($invoice->invoice_date)) : '-',
                '{due_date}' => $invoice->due_date ? date('Y-m-d', strtotime($invoice->due_date)) : '-',
                '{old_invoice_status}' => ucfirst($event->oldStatus),
                '{new_invoice_status}' => ucfirst($event->newStatus),
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_user_email}' => $assignedUser->email ?? '-',
                '{company_name}' => getCompanyName(),
                '{view_link}' => route('invoices.public', ['invoice' => encrypt($invoice->id)]),
            ];

            try {
                session()->forget('email_error');

                // Generate Invoice PDF Attachment
                $pdfPath = storage_path('app/temp_invoice_status_' . $invoice->id . '_' . time() . '.pdf');

                // Pre-load relationships
                $invoice->loadMissing(['products.tax', 'account', 'contact', 'payments']);

                \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', compact('invoice'))->save($pdfPath);

                $createdByUser = User::find(createdBy());
                $userLanguage = $createdByUser->lang ?? 'en';

                // Send email to contact if exists
                if ($contact && $contact->email) {
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Invoice Status Changed',
                        variables: $variables,
                        toEmail: $contact->email,
                        toName: $contact->name,
                        language: $userLanguage,
                        attachmentPath: $pdfPath,
                        attachmentName: 'Invoice_' . ($invoice->invoice_number ?: $invoice->id) . '.pdf'
                    );
                }

                // Send email to assigned user if exists and different from contact
                if ($assignedUser && $assignedUser->email && (!$contact || $assignedUser->email !== $contact->email)) {
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Invoice Status Changed',
                        variables: $variables,
                        toEmail: $assignedUser->email,
                        toName: $assignedUser->name,
                        language: $userLanguage,
                        attachmentPath: $pdfPath,
                        attachmentName: 'Invoice_' . ($invoice->invoice_number ?: $invoice->id) . '.pdf'
                    );
                }

                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                if (
                    !str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')
                ) {
                    session()->flash('email_error', 'Failed to send invoice status changed email: ' . $errorMessage);
                }

                if (isset($pdfPath) && file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            }
        }
    }
}
