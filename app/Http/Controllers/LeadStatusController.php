<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = LeadStatus::query()
            ->where('created_by', createdBy());

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('order', 'asc')->orderBy('id', 'asc');
        }

        $leadStatuses = $query->paginate($request->per_page ?? 10);

        return Inertia::render('lead-statuses/index', [
            'leadStatuses' => $leadStatuses,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        LeadStatus::create($validated);

        return redirect()->back()->with('success', __('Lead status created successfully.'));
    }

    public function update(Request $request, $leadStatusId)
    {
        $leadStatus = LeadStatus::where('id', $leadStatusId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadStatus) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'color' => 'required|string|max:7',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);

                if (isset($validated['status']) && $validated['status'] === 'inactive' && $leadStatus->status === 'active') {
                    if ($leadStatus->leads()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate lead status that is currently assigned to one or more leads.'));
                    }
                }

                $leadStatus->update($validated);

                return redirect()->back()->with('success', __('Lead status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead status not found.'));
        }
    }

    public function destroy($leadStatusId)
    {
        $leadStatus = LeadStatus::where('id', $leadStatusId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadStatus) {
            try {
                if ($leadStatus->leads()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete lead status that is currently assigned to one or more leads.'));
                }

                $leadStatus->delete();
                return redirect()->back()->with('success', __('Lead status deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete lead status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead status not found.'));
        }
    }

    public function toggleStatus($leadStatusId)
    {
        $leadStatus = LeadStatus::where('id', $leadStatusId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadStatus) {
            try {
                if ($leadStatus->status === 'active') {
                    if ($leadStatus->leads()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate lead status that is currently assigned to one or more leads.'));
                    }
                    $leadStatus->status = 'inactive';
                } else {
                    $leadStatus->status = 'active';
                }
                $leadStatus->save();

                return redirect()->back()->with('success', __('Lead status status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead status status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead status not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-lead-statuses')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $statuses = \App\Models\LeadStatus::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();

            if ($statuses->isEmpty()) {
                return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }

            $inUse = $statuses->filter(fn($s) => $s->leads()->count() > 0);
            $deletable = $statuses->filter(fn($s) => $s->leads()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to leads.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected lead statuses because they are currently assigned to leads.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:lead_statuses,id',
            'items.*.order' => 'required|integer',
        ]);

        try {
            foreach ($validated['items'] as $item) {
                LeadStatus::where('id', $item['id'])
                    ->where('created_by', createdBy())
                    ->update(['order' => $item['order']]);
            }
            return response()->json(['message' => __('Order updated successfully.')]);
        } catch (\Exception $e) {
            return response()->json(['message' => __('Failed to update order.')], 500);
        }
    }
}