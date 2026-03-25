<?php

namespace App\Exports;

use App\Models\ProjectTask;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectTaskExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = ProjectTask::with(['project', 'parent', 'taskStatus', 'assignedUser'])
            ->where('created_by', createdBy());

        if ($this->request) {
            if ($this->request->filled('search')) {
                $search = $this->request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($this->request->filled('status') && $this->request->status !== 'all') {
                $query->where('task_status_id', $this->request->status);
            }

            if ($this->request->filled('priority') && $this->request->priority !== 'all') {
                $query->where('priority', $this->request->priority);
            }

            if ($this->request->filled('project_id') && $this->request->project_id !== 'all') {
                $query->where('project_id', $this->request->project_id);
            }

            if ($this->request->filled('assigned_to') && $this->request->assigned_to !== 'all') {
                if ($this->request->assigned_to === 'unassigned') {
                    $query->whereNull('assigned_to');
                } else {
                    $query->where('assigned_to', $this->request->assigned_to);
                }
            }
        }

        // Apply the original 'when' condition after all other filters
        $query->when(!auth()->user()->hasRole('company'), function ($q) {
            $q->where('assigned_to', auth()->id());
        });

        return $query->get();
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