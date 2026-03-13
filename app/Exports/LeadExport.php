<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadExport implements FromCollection, WithHeadings
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Lead::with(['leadStatus', 'leadSource', 'assignedUser', 'campaign', 'accountIndustry'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        if ($this->request) {
            if ($this->request->has('search') && !empty($this->request->search)) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->request->search . '%')
                        ->orWhere('company', 'like', '%' . $this->request->search . '%')
                        ->orWhere('email', 'like', '%' . $this->request->search . '%');
                });
            }

            if ($this->request->has('lead_status_id') && !empty($this->request->lead_status_id) && $this->request->lead_status_id !== 'all') {
                $query->where('lead_status_id', $this->request->lead_status_id);
            }

            if ($this->request->has('lead_source_id') && !empty($this->request->lead_source_id) && $this->request->lead_source_id !== 'all') {
                $query->where('lead_source_id', $this->request->lead_source_id);
            }

            if ($this->request->has('status') && !empty($this->request->status) && $this->request->status !== 'all') {
                $query->where('status', $this->request->status);
            }

            if ($this->request->has('is_converted') && $this->request->is_converted !== 'all') {
                $query->where('is_converted', $this->request->is_converted === 'true' || $this->request->is_converted === '1');
            }

            if ($this->request->has('assigned_to') && !empty($this->request->assigned_to) && $this->request->assigned_to !== 'all') {
                $query->where('assigned_to', $this->request->assigned_to);
            }

            if ($this->request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $this->request->date_from);
            }

            if ($this->request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $this->request->date_to);
            }
        }

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
                    'created at' => $lead->created_at?->format('Y-m-d'),
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
            'Created At',
        ];
    }
}
