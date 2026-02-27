<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Models\User;
use App\Services\EmailTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Exception;

class SendInvoiceCreatedEmail
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
    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        $contact = $invoice->contact;
        $assignedUser = $invoice->assignedUser;
        $account = $invoice->account;

        if (isEmailTemplateEnabled('Invoice Created', createdBy()) && $invoice->contact_id) {
            // Prepare email variables
            $variables = [
                '{invoice_number}' => $invoice->invoice_number ?? '-',
                '{invoice_name}' => $invoice->name ?? '-',
                '{contact_name}' => $contact->name ?? '-',
                '{account_name}' => $account->name ?? '-',
                '{invoice_total}' => $invoice->total_amount ? formatCurrency((float) $invoice->total_amount, createdBy()) : formatCurrency(0, createdBy()),
                '{invoice_date}' => $invoice->invoice_date ? date('Y-m-d', strtotime($invoice->invoice_date)) : '-',
                '{due_date}' => $invoice->due_date ? date('Y-m-d', strtotime($invoice->due_date)) : '-',
                '{invoice_status}' => ucfirst($invoice->status ?? 'draft'),
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_user_email}' => $assignedUser->email ?? '-',
                '{company_name}' => getCompanyName(),
                '{view_link}' => route('invoices.public', ['invoice' => encrypt($invoice->id)]),
            ];

            try {
                session()->forget('email_error');

                // Generate Invoice PDF Attachment
                $pdfPath = storage_path('app/temp_invoice_' . $invoice->id . '_' . time() . '.pdf');

                // Pre-load relationships
                $invoice->loadMissing(['products.tax', 'account', 'contact', 'payments']);

                Pdf::loadView('pdf.invoice', compact('invoice'))->save($pdfPath);

                // Send email to contact if exists
                if ($contact && $contact->email) {
                    $createdByUser = User::find(createdBy());
                    $userLanguage = $createdByUser->lang ?? 'en';

                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Invoice Created',
                        variables: $variables,
                        toEmail: $contact->email,
                        toName: $contact->name,
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
                    session()->flash('email_error', 'Failed to send invoice created email: ' . $errorMessage);
                }

                if (isset($pdfPath) && file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            }
        }
    }
}
