<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $query = Lead::with(['leadStatus', 'leadSource', 'assignedUser', 'campaign', 'accountIndustry'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });
        
        return $query->get()
            ->map(function ($lead) {
                return [
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company,
                    'account name' => $lead->account_name,
                    'account industry' => $lead->accountIndustry?->name,
                    'website' => $lead->website,
                    'position' => $lead->position,
                    'address' => $lead->address,
                    'notes' => $lead->notes,
                    'lead value' => $lead->value,
                    'status' => $lead->status,
                    'is converted' => $lead->is_converted ? 'Yes' : 'No',
                    'lead status' => $lead->leadStatus?->name,
                    'lead source' => $lead->leadSource?->name,
                    'assigned to' => $lead->assignedUser?->name,
                    'campaign' => $lead->campaign?->name,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Company',
            'Account Name',
            'Account Industry',
            'Website',
            'Position',
            'Address',
            'Notes',
            'Lead Value',
            'Status',
            'Is Converted',
            'Lead Status',
            'Lead Source',
            'Assigned User',
            'Campaign',
        ];
    }
}
