<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Project::with(['account', 'assignedUser'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->get();
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