<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Account;
use App\Exports\ProjectExport;
use Maatwebsite\Excel\Facades\Excel;


use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()
            ->with(['account', 'assignedUser', 'creator'])
            ->where(function($q) {
                if (auth()->user()->type === 'company') {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle filters
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && !empty($request->priority) && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('account_id') && !empty($request->account_id) && $request->account_id !== 'all') {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $projects = $query->paginate($request->per_page ?? 10);

        // Get data for dropdowns
        $accounts = Account::where('created_by', createdBy())->where('status', 'active')
            ->when(auth()->user()->type !== 'company', function($q) {
                $q->where('assigned_to', auth()->id());
            })->get(['id', 'name']);

        $users = [];
        if (auth()->user()->type === 'company') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'accounts' => $accounts,
            'users' => $users,
            'filters' => $request->all(['search', 'status', 'priority', 'account_id', 'assigned_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $project = Project::with(['account', 'assignedUser', 'creator'])
            ->where('created_by', createdBy())
            ->findOrFail($id);

        // Get task statistics
        $taskStats = \App\Models\ProjectTask::with('taskStatus')
            ->where('project_id', $id)
            ->where('created_by', createdBy())
            ->get()
            ->groupBy('taskStatus.name')
            ->map(function($tasks) {
                return $tasks->count();
            })
            ->toArray();

        $totalTasks = array_sum($taskStats);
        $completedTasks = $taskStats['Done'] ?? 0;
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        $parentMeetings = \App\Models\Meeting::with(['assignedUser'])
            ->where('parent_module', 'project')
            ->where('parent_id', $id)
            ->where('created_by', createdBy())
            ->get();

        // Get related calls
        $parentCalls = \App\Models\Call::with(['assignedUser'])
            ->where('parent_module', 'project')
            ->where('parent_id', $id)
            ->where('created_by', createdBy())
            ->get()
            ->map(function($call) {
                $call->type = 'call';
                return $call;
            });

        $meetings = $parentMeetings->merge($parentCalls)->sortByDesc('start_date')->values();

        // Get all task statuses for dynamic display
        $taskStatuses = \App\Models\TaskStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name', 'color']);

        return Inertia::render('projects/show', [
            'project' => $project,
            'taskStats' => $taskStats,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'progressPercentage' => $progressPercentage,
            'meetings' => $meetings,
            'taskStatuses' => $taskStatuses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value && Project::where('code', $value)->where('created_by', createdBy())->exists()) {
                    $fail('The code has already been taken.');
                }
            }],
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:active,inactive,completed,on_hold',
            'account_id' => 'required|exists:accounts,id',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $validated['created_by'] = createdBy();

        if (auth()->user()->type != 'company') {
            $validated['assigned_to'] = auth()->id();
        }

        Project::create($validated);

        return redirect()->back()->with('success', __('Project created successfully.'));
    }

    public function update(Request $request, $projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', createdBy())
            ->first();

        if ($project) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'code' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) use ($projectId) {
                        if ($value && Project::where('code', $value)->where('created_by', createdBy())->where('id', '!=', $projectId)->exists()) {
                            $fail('The code has already been taken.');
                        }
                    }],
                    'description' => 'nullable|string',
                    'start_date' => 'nullable|date',
                    'end_date' => 'nullable|date|after_or_equal:start_date',
                    'budget' => 'nullable|numeric|min:0',
                    'priority' => 'nullable|in:low,medium,high,urgent',
                    'status' => 'nullable|in:active,inactive,completed,on_hold',
                    'account_id' => 'required|exists:accounts,id',
                    'assigned_to' => 'nullable|exists:users,id',
                ]);

                if (auth()->user()->type != 'company') {
                    $validated['assigned_to'] = auth()->id();
                }

                $project->update($validated);

                return redirect()->back()->with('success', __('Project updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update project.'));
            }
        } else {
            return redirect()->back()->with('error', __('Project not found.'));
        }
    }

    public function destroy($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', createdBy())
            ->first();

        if ($project) {
            try {
                $project->delete();
                return redirect()->back()->with('success', __('Project deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete project.'));
            }
        } else {
            return redirect()->back()->with('error', __('Project not found.'));
        }
    }

    public function toggleStatus($projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('created_by', createdBy())
            ->first();

        if ($project) {
            try {
                $newStatus = $project->status === 'active' ? 'inactive' : 'active';
                $project->update(['status' => $newStatus]);

                return redirect()->back()->with('success', __('Project status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update project status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Project not found.'));
        }
    }

    public function fileExport()
    {
        if (!auth()->user()->can('export-projects')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $name = 'project_' . date('Y-m-d_H-i-s');
        
        ob_start();
        
        $data = Excel::download(new ProjectExport(), $name . '.xlsx');
        ob_end_clean();

        return $data;
    }
}
