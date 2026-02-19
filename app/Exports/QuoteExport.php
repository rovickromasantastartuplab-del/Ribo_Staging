<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Quote;

class QuoteExport implements FromCollection,WithHeadings
{
    public function collection()
    {
        $query = Quote::with(['opportunity', 'account', 'billingContact', 'shippingContact', 'shippingProviderType', 'assignedUser', 'creator'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        $data = $query->get();

        foreach($data as $k => $quote) {
            $opportunity = $quote->opportunity ? $quote->opportunity->name : '';
            $account = $quote->account ? $quote->account->name : '';
            $billing_contact = $quote->billingContact ? $quote->billingContact->name : '';
            $shipping_contact = $quote->shippingContact ? $quote->shippingContact->name : '';
            $shipping_provider_type = $quote->shippingProviderType ? $quote->shippingProviderType->name : '';
            $assigned_user = $quote->assignedUser ? $quote->assignedUser->name : '';

            $data[$k] = [
                $quote->quote_number,
                $quote->name,
                $quote->description,
                $opportunity,
                $account,
                $billing_contact,
                $shipping_contact,
                $shipping_provider_type,
                $quote->billing_address,
                $quote->billing_city,
                $quote->billing_state,
                $quote->billing_postal_code,
                $quote->billing_country,
                $quote->shipping_address,
                $quote->shipping_city,
                $quote->shipping_state,
                $quote->shipping_postal_code,
                $quote->shipping_country,
                $quote->subtotal,
                $quote->discount_amount,
                $quote->total_amount,
                $quote->status,
                $quote->valid_until,
                $assigned_user
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            "Quote Number",
            "Name",
            "Description",
            "Opportunity",
            "Account",
            "Billing Contact",
            "Shipping Contact",
            "Shipping Provider Type",
            "Billing Address",
            "Billing City",
            "Billing State",
            "Billing Postal Code",
            "Billing Country",
            "Shipping Address",
            "Shipping City",
            "Shipping State",
            "Shipping Postal Code",
            "Shipping Country",
            "Subtotal",
            "Discount Amount",
            "Total Amount",
            "Status",
            "Valid Until",
            "Assigned User"
        ];
    }
}
