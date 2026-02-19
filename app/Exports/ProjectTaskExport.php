<?php

namespace App\Exports;

use App\Models\ProjectTask;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectTaskExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return ProjectTask::with(['project', 'assignedUser', 'taskStatus', 'parent'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->get();
    }

    public function headings(): array
    {
        return [
            'Title',
            'Description',
            'Project',
            'Parent Task',
            'Priority',
            'Status',
            'Assigned To',
            'Start Date',
            'Due Date',
            'Estimated Hours',
            'Actual Hours',
            'Progress (%)',
            'Created At'
        ];
    }

    public function map($task): array
    {
        return [
            $task->title,
            $task->description,
            $task->project->name ?? '',
            $task->parent->title ?? '',
            ucfirst($task->priority),
            $task->taskStatus->name ?? '',
            $task->assignedUser->name ?? 'Unassigned',
            $task->start_date,
            $task->due_date,
            $task->estimated_hours,
            $task->actual_hours,
            $task->progress,
            $task->created_at->format('Y-m-d H:i:s')
        ];
    }
}