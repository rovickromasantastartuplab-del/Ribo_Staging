<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Project::with(['account', 'assignedUser'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        if ($this->request) {
            if ($this->request->filled('search')) {
                $search = $this->request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($this->request->filled('status') && $this->request->status !== 'all') {
                $query->where('status', $this->request->status);
            }

            if ($this->request->filled('priority') && $this->request->priority !== 'all') {
                $query->where('priority', $this->request->priority);
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
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Code',
            'Description',
            'Account',
            'Start Date',
            'End Date',
            'Budget',
            'Priority',
            'Status',
            'Assigned To',
            'Created At'
        ];
    }

    public function map($project): array
    {
        return [
            $project->name,
            $project->code,
            $project->description,
            $project->account->name ?? '',
            $project->start_date,
            $project->end_date,
            $project->budget,
            ucfirst($project->priority),
            ucfirst($project->status),
            $project->assignedUser->name ?? 'Unassigned',
            $project->created_at->format('Y-m-d H:i:s')
        ];
    }
}