<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Models\Project;
use App\Models\User;
use App\Models\TaskStatus;
use App\Exports\ProjectTaskExport;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ProjectTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = ProjectTask::query()
            ->with(['project', 'assignedUser', 'creator', 'parent', 'taskStatus'])
            ->where(function ($q) {
                if (auth()->user()->type === 'company') {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('task_status_id', $request->status);
        }

        if ($request->has('priority') && !empty($request->priority) && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('project_id') && !empty($request->project_id) && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $tasks = $query->paginate($request->per_page ?? 10);

        $projects = Project::where('created_by', createdBy())->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function ($q) {
                $q->where('assigned_to', auth()->id());
            })->get(['id', 'name']);

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        $parentTasks = [];

        $taskStatuses = TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name', 'color')
            ->get();

        return Inertia::render('project-tasks/index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users,
            'parentTasks' => $parentTasks,
            'taskStatuses' => $taskStatuses,
            'filters' => $request->all(['search', 'status', 'priority', 'project_id', 'assigned_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $task = ProjectTask::with(['project', 'assignedUser', 'creator', 'parent', 'subtasks.assignedUser', 'taskStatus'])
            ->where('created_by', createdBy())
            ->findOrFail($id);

        $taskStatuses = TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name', 'color')
            ->get();

        return Inertia::render('project-tasks/show', [
            'task' => $task,
            'taskStatuses' => $taskStatuses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'parent_id' => 'nullable|exists:project_tasks,id',
            'assigned_to' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'task_status_id' => 'nullable|integer|exists:task_statuses,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        $validated['created_by'] = createdBy();

        if (auth()->user()->type != 'company') {
            $validated['assigned_to'] = auth()->id();
        } else {
            // Convert empty string or 'unassigned' to null
            if (empty($validated['assigned_to']) || $validated['assigned_to'] === 'unassigned') {
                $validated['assigned_to'] = null;
            }
        }

        // Set default task status if not provided
        if (!isset($validated['task_status_id'])) {
            $defaultStatus = TaskStatus::where('created_by', createdBy())
                ->where('name', 'To Do')
                ->first();
            if ($defaultStatus) {
                $validated['task_status_id'] = $defaultStatus->id;
            }
        }

        $task = ProjectTask::create($validated);
        if (isEmailTemplateEnabled('Task Assigned', createdBy()) && $task && $task->assigned_to && !IsDemo()) {
            event(new \App\Events\TaskAssigned($task));
        }

        return redirect()->back()->with('success', __('Task created successfully.'));
    }

    public function update(Request $request, $taskId)
    {
        $task = ProjectTask::where('id', $taskId)
            ->where('created_by', createdBy())
            ->first();

        if ($task) {
            try {
                $validated = $request->validate([
                    'title' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'project_id' => 'required|exists:projects,id',
                    'parent_id' => 'nullable|exists:project_tasks,id',
                    'assigned_to' => 'nullable|exists:users,id',
                    'start_date' => 'nullable|date',
                    'due_date' => 'nullable|date|after_or_equal:start_date',
                    'priority' => 'nullable|in:low,medium,high,urgent',
                    'task_status_id' => 'nullable|integer',
                    'estimated_hours' => 'nullable|numeric|min:0',
                    'actual_hours' => 'nullable|numeric|min:0',
                    'progress' => 'nullable|integer|min:0|max:100',
                ]);

                if (auth()->user()->type != 'company') {
                    $validated['assigned_to'] = auth()->id();
                } else {
                    // Convert empty string or 'unassigned' to null
                    if (empty($validated['assigned_to']) || $validated['assigned_to'] === 'unassigned') {
                        $validated['assigned_to'] = null;
                    }
                }

                // Validate task_status_id belongs to current user
                if (!empty($validated['task_status_id'])) {
                    $statusExists = TaskStatus::where('id', $validated['task_status_id'])
                        ->where('created_by', createdBy())
                        ->where('status', 'active')
                        ->exists();

                    if (!$statusExists) {
                        return redirect()->back()->with('error', __('Invalid task status.'));
                    }
                }

                $task->update($validated);

                return redirect()->back()->with('success', __('Task updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update task.'));
            }
        } else {
            return redirect()->back()->with('error', __('Task not found.'));
        }
    }

    public function destroy($taskId)
    {
        $task = ProjectTask::with('taskStatus')
            ->where('id', $taskId)
            ->where('created_by', createdBy())
            ->first();

        if ($task) {
            // Prevent deletion if task is in specific status
            if ($task->taskStatus && in_array($task->taskStatus->name, ['In Progress', 'Review'])) {
                return redirect()->back()->with('error', __('Cannot delete task in ' . $task->taskStatus->name . ' status.'));
            }

            try {
                $task->delete();
                return redirect()->back()->with('success', __('Task deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete task.'));
            }
        } else {
            return redirect()->back()->with('error', __('Task not found.'));
        }
    }

    public function toggleStatus($taskId)
    {
        $task = ProjectTask::where('id', $taskId)
            ->where('created_by', createdBy())
            ->first();

        if ($task) {
            try {
                // Get To Do and Done status IDs
                $toDoStatus = TaskStatus::where('created_by', createdBy())->where('name', 'To Do')->first();
                $doneStatus = TaskStatus::where('created_by', createdBy())->where('name', 'Done')->first();

                if (!$toDoStatus || !$doneStatus) {
                    return redirect()->back()->with('error', __('Required task statuses not found.'));
                }

                $newStatusId = $task->task_status_id === $toDoStatus->id ? $doneStatus->id : $toDoStatus->id;
                $task->update(['task_status_id' => $newStatusId]);

                return redirect()->back()->with('success', __('Task status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update task status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Task not found.'));
        }
    }

    public function kanban($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', createdBy())
            ->firstOrFail();

        $statuses = TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name', 'color')
            ->get()
            ->map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color
                ];
            })
            ->toArray();

        $tasks = ProjectTask::with(['assignedUser', 'creator', 'taskStatus'])
            ->where('project_id', $projectId)
            ->where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'progress' => $task->progress,
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'task_status_id' => $task->task_status_id,
                    'assigned_user' => $task->assignedUser,
                    'created_at' => $task->created_at,
                ];
            })
            ->groupBy('task_status_id');

        $kanbanData = [];
        foreach ($statuses as $status) {
            $kanbanData[$status['id']] = [
                'status' => $status,
                'tasks' => $tasks->get($status['id'], collect())->values()->toArray()
            ];
        }

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        $allTaskStatuses = TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name', 'color')
            ->get();

        return Inertia::render('projects/kanban', [
            'project' => $project,
            'kanbanData' => $kanbanData,
            'statuses' => $statuses,
            'users' => $users,
            'taskStatuses' => $allTaskStatuses,
        ]);
    }

    public function gantt($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', createdBy())
            ->firstOrFail();

        $tasks = ProjectTask::with(['assignedUser', 'creator', 'taskStatus'])
            ->where('project_id', $projectId)
            ->where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->orderBy('start_date')
            ->get();

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        $taskStatuses = TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->select('id', 'name', 'color')
            ->get();

        return Inertia::render('projects/gantt', [
            'project' => $project,
            'tasks' => $tasks,
            'users' => $users,
            'taskStatuses' => $taskStatuses,
        ]);
    }

    public function updateStatus($taskId)
    {
        $task = ProjectTask::where('id', $taskId)
            ->where('created_by', createdBy())
            ->first();

        if (!$task) {
            return back()->with('error', __('Task not found.'));
        }

        $validated = request()->validate([
            'task_status_id' => 'required|exists:task_statuses,id'
        ]);

        $task->update(['task_status_id' => $validated['task_status_id']]);

        return back()->with('success', __('Task status updated successfully.'));
    }

    public function getParentTasks($projectId)
    {
        $parentTasks = ProjectTask::where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->where('project_id', $projectId)
            ->whereNull('parent_id')
            ->select('id', 'title')
            ->get();

        return response()->json($parentTasks);
    }

    public function fileExport()
    {
        if (!auth()->user()->can('export-project-tasks')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $name = 'project_tasks_' . date('Y-m-d_H-i-s');
        ob_start();
        $data = Excel::download(new ProjectTaskExport(), $name . '.xlsx');
        ob_end_clean();

        return $data;
    }

    public function getProjectDetails($projectId)
    {
        $parentTasks = ProjectTask::where('created_by', createdBy())
            ->when(auth()->user()->type !== 'company', function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->where('project_id', $projectId)
            ->whereNull('parent_id')
            ->select('id', 'title')
            ->get();

        return response()->json([
            'parent_tasks' => $parentTasks
        ]);
    }
}
