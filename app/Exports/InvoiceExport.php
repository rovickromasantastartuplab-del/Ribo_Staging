<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InvoiceExport implements FromCollection, WithHeadings
{
    public function __construct(private Request $request) {}

    public function collection()
    {
        $query = Invoice::with(['salesOrder', 'quote', 'opportunity', 'account', 'contact', 'assignedUser'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%')
                  ->orWhereHas('account', fn($q) => $q->where('name', 'like', '%' . $search . '%'));
            });
        }

        if ($this->request->filled('status') && $this->request->status !== 'all') {
            $query->where('status', $this->request->status);
        }

        if ($this->request->filled('account_id') && $this->request->account_id !== 'all') {
            $query->where('account_id', $this->request->account_id);
        }

        if ($this->request->filled('assigned_to') && $this->request->assigned_to !== 'all') {
            if ($this->request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $this->request->assigned_to);
            }
        }

        return $query->get()
            ->map(function ($invoice) {
                return [
                    'invoice_number' => $invoice->invoice_number,
                    'name' => $invoice->name,
                    'description' => $invoice->description,
                    'sales_order' => $invoice->salesOrder?->order_number,
                    'quote' => $invoice->quote?->name,
                    'opportunity' => $invoice->opportunity?->name,
                    'account' => $invoice->account?->name,
                    'contact' => $invoice->contact?->name,
                    'billing_address' => $invoice->billing_address,
                    'billing_city' => $invoice->billing_city,
                    'billing_state' => $invoice->billing_state,
                    'billing_postal_code' => $invoice->billing_postal_code,
                    'billing_country' => $invoice->billing_country,
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'payment_method' => $invoice->payment_method,
                    'notes' => $invoice->notes,
                    'terms' => $invoice->terms,
                    'assigned_user' => $invoice->assignedUser?->name,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Name',
            'Description',
            'Sales Order',
            'Quote',
            'Opportunity',
            'Account',
            'Contact',
            'Billing Address',
            'Billing City',
            'Billing State',
            'Billing Postal Code',
            'Billing Country',
            'Subtotal',
            'Tax Amount',
            'Discount Amount',
            'Total Amount',
            'Status',
            'Payment Method',
            'Notes',
            'Terms',
            'Assigned User',
            'Invoice Date',
            'Due Date',
        ];
    }
}
