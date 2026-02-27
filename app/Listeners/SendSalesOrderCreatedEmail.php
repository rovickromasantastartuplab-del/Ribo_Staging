<?php

namespace App\Listeners;

use App\Events\SalesOrderCreated;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendSalesOrderCreatedEmail
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
    public function handle(SalesOrderCreated $event): void
    {
        $salesOrder = $event->salesOrder;
        $billingContact = $salesOrder->billingContact;
        $assignedUser = $salesOrder->assignedUser;
        $account = $salesOrder->account;

        if (isEmailTemplateEnabled('Sales Order Created', createdBy()) && $salesOrder->billing_contact_id) {
            // Prepare email variables
            $variables = [
                '{order_number}' => $salesOrder->order_number ?? '-',
                '{order_name}' => $salesOrder->name ?? '-',
                '{billing_contact_name}' => $billingContact->name ?? '-',
                '{account_name}' => $account->name ?? '-',
                '{order_total}' => $salesOrder->total_amount ? formatCurrency((float) $salesOrder->total_amount, createdBy()) : formatCurrency(0, createdBy()),
                '{order_date}' => $salesOrder->order_date ? date('Y-m-d', strtotime($salesOrder->order_date)) : '-',
                '{delivery_date}' => $salesOrder->delivery_date ? date('Y-m-d', strtotime($salesOrder->delivery_date)) : '-',
                '{order_status}' => ucfirst($salesOrder->status ?? 'draft'),
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_user_email}' => $assignedUser->email ?? '-',
                '{company_name}' => getCompanyName(),
                '{view_link}' => route('sales-orders.public', ['salesOrder' => encrypt($salesOrder->id)]),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Generate Sales Order PDF Attachment
                $pdfPath = storage_path('app/temp_salesorder_' . $salesOrder->id . '_' . time() . '.pdf');

                // Pre-load relationships that the PDF requires
                $salesOrder->loadMissing(['products.tax', 'account', 'billingContact', 'shippingContact']);

                \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.sales_order', compact('salesOrder'))->save($pdfPath);

                // Send email to billing contact if exists
                if ($billingContact && $billingContact->email) {
                    $createdByUser = User::find(createdBy());
                    $userLanguage = $createdByUser->lang ?? 'en';
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Sales Order Created',
                        variables: $variables,
                        toEmail: $billingContact->email,
                        toName: $billingContact->name,
                        language: $userLanguage,
                        attachmentPath: $pdfPath,
                        attachmentName: 'SalesOrder_' . ($salesOrder->order_number ?: $salesOrder->id) . '.pdf'
                    );
                }

                // Clean up the temporary PDF file
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
                    session()->flash('email_error', 'Failed to send sales order created email: ' . $errorMessage);
                }
            }
        }
    }
}
