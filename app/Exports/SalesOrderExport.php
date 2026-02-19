<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrderExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $query = SalesOrder::with(['quote', 'account', 'contact', 'billingContact', 'shippingContact', 'shippingProviderType', 'assignedUser', 'creator'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

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
