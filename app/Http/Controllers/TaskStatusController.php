<?php

namespace App\Http\Controllers;

use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaskStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = TaskStatus::query()->where('created_by', createdBy());

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $taskStatuses = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return Inertia::render('task-statuses/index', [
            'taskStatuses' => $taskStatuses,
            'filters' => $request->only(['search', 'status', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();

        TaskStatus::create($validated);

        return redirect()->back()->with('success', __('Task status created successfully'));
    }

    public function update(Request $request, TaskStatus $taskStatus)
    {
        if ($taskStatus->created_by !== createdBy()) {
            return redirect()->back()->with('error', __('Unauthorized'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $taskStatus->update($validated);

        return redirect()->back()->with('success', __('Task status updated successfully'));
    }

    public function destroy(TaskStatus $taskStatus)
    {
        if ($taskStatus->created_by !== createdBy()) {
            return redirect()->back()->with('error', __('Unauthorized'));
        }

        // Check if any tasks are using this status
        $taskCount = \App\Models\ProjectTask::where('task_status_id', $taskStatus->id)->count();
        
        if ($taskCount > 0) {
            return redirect()->back()->with('error', __('Cannot delete task status. ' . $taskCount . ' task(s) are using this status.'));
        }

        $taskStatus->delete();

        return redirect()->back()->with('success', __('Task status deleted successfully'));
    }

    public function toggleStatus($taskStatusId)
    {
        $taskStatus = TaskStatus::where('id', $taskStatusId)
            ->where('created_by', createdBy())
            ->first();

        if ($taskStatus) {
            try {
                $taskStatus->status = $taskStatus->status === 'active' ? 'inactive' : 'active';
                $taskStatus->save();

                return redirect()->back()->with('success', __('Task status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update task status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Task status not found.'));
        }
    }
}