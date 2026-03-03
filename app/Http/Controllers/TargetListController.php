<?php

namespace App\Http\Controllers;

use App\Models\TargetList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TargetListController extends Controller
{
    public function index(Request $request)
    {
        $query = TargetList::query()
            ->where('created_by', createdBy());

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $targetLists = $query->paginate($request->per_page ?? 10);

        return Inertia::render('target-lists/index', [
            'targetLists' => $targetLists,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        TargetList::create($validated);

        return redirect()->back()->with('success', __('Target list created successfully.'));
    }

    public function update(Request $request, $targetListId)
    {
        $targetList = TargetList::where('id', $targetListId)
            ->where('created_by', createdBy())
            ->first();

        if ($targetList) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $targetList->update($validated);

                return redirect()->back()->with('success', __('Target list updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update target list.'));
            }
        } else {
            return redirect()->back()->with('error', __('Target list not found.'));
        }
    }

    public function destroy($targetListId)
    {
        $targetList = TargetList::where('id', $targetListId)
            ->where('created_by', createdBy())
            ->first();

        if ($targetList) {
            try {
                $targetList->delete();
                return redirect()->back()->with('success', __('Target list deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete target list.'));
            }
        } else {
            return redirect()->back()->with('error', __('Target list not found.'));
        }
    }

    public function toggleStatus($targetListId)
    {
        $targetList = TargetList::where('id', $targetListId)
            ->where('created_by', createdBy())
            ->first();

        if ($targetList) {
            try {
                $targetList->status = $targetList->status === 'active' ? 'inactive' : 'active';
                $targetList->save();

                return redirect()->back()->with('success', __('Target list status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update target list status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Target list not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-target-lists')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $query = \App\Models\TargetList::whereIn('id', $validated['ids'])->where('created_by', createdBy());
            $count = $query->count();
            
            if ($count === 0) {
                 return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }
            
            $query->delete();
            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $count]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}