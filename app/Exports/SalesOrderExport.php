<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrderExport implements FromCollection, WithHeadings
{
    public function __construct(private Request $request) {}

    public function collection()
    {
        $query = SalesOrder::with(['quote', 'account', 'contact', 'billingContact', 'shippingContact', 'shippingProviderType', 'assignedUser', 'creator'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
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
            ->map(function ($salesOrder) {
                return [
                    'order_number' => $salesOrder->order_number,
                    'name' => $salesOrder->name,
                    'description' => $salesOrder->description,
                    'quote' => $salesOrder->quote?->name,
                    'account' => $salesOrder->account?->name,
                    'contact' => $salesOrder->contact?->name,
                    'billing_contact' => $salesOrder->billingContact?->name,
                    'shipping_contact' => $salesOrder->shippingContact?->name,
                    'shipping_provider_type' => $salesOrder->shippingProviderType?->name,
                    'billing_address' => $salesOrder->billing_address,
                    'billing_city' => $salesOrder->billing_city,
                    'billing_state' => $salesOrder->billing_state,
                    'billing_postal_code' => $salesOrder->billing_postal_code,
                    'billing_country' => $salesOrder->billing_country,
                    'shipping_address' => $salesOrder->shipping_address,
                    'shipping_city' => $salesOrder->shipping_city,
                    'shipping_state' => $salesOrder->shipping_state,
                    'shipping_postal_code' => $salesOrder->shipping_postal_code,
                    'shipping_country' => $salesOrder->shipping_country,
                    'order_date' => $salesOrder->order_date?->format('Y-m-d'),
                    'delivery_date' => $salesOrder->delivery_date?->format('Y-m-d'),
                    'status' => $salesOrder->status,
                    'subtotal' => $salesOrder->subtotal,
                    'tax_amount' => $salesOrder->tax_amount,
                    'shipping_amount' => $salesOrder->shipping_amount,
                    'discount_amount' => $salesOrder->discount_amount,
                    'total_amount' => $salesOrder->total_amount,
                    'assigned_user' => $salesOrder->assignedUser?->name,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Name',
            'Description',
            'Quote',
            'Account',
            'Contact',
            'Billing Contact',
            'Shipping Contact',
            'Shipping Provider Type',
            'Billing Address',
            'Billing City',
            'Billing State',
            'Billing Postal Code',
            'Billing Country',
            'Shipping Address',
            'Shipping City',
            'Shipping State',
            'Shipping Postal Code',
            'Shipping Country',
            'Order Date',
            'Delivery Date',
            'Status',
            'Subtotal',
            'Tax Amount',
            'Shipping Amount',
            'Discount Amount',
            'Total Amount',
            'Assigned User',
        ];
    }
}
