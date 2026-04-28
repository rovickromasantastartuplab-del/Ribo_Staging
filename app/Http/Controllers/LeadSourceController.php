<?php

namespace App\Http\Controllers;

use App\Models\LeadSource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadSourceController extends Controller
{
    public function index(Request $request)
    {
        $query = LeadSource::query()
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
            $query->orderBy('id', 'desc');
        }

        $leadSources = $query->paginate($request->per_page ?? 10);

        return Inertia::render('lead-sources/index', [
            'leadSources' => $leadSources,
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

        LeadSource::create($validated);

        return redirect()->back()->with('success', __('Lead source created successfully.'));
    }

    public function update(Request $request, $leadSourceId)
    {
        $leadSource = LeadSource::where('id', $leadSourceId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadSource) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);
                
                $leadSource->update($validated);

                return redirect()->back()->with('success', __('Lead source updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead source.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead source not found.'));
        }
    }

    public function destroy($leadSourceId)
    {
        $leadSource = LeadSource::where('id', $leadSourceId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadSource) {
            try {
                if ($leadSource->leads()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete lead source that is currently assigned to one or more leads.'));
                }

                $leadSource->delete();
                return redirect()->back()->with('success', __('Lead source deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete lead source.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead source not found.'));
        }
    }

    public function toggleStatus($leadSourceId)
    {
        $leadSource = LeadSource::where('id', $leadSourceId)
            ->where('created_by', createdBy())
            ->first();

        if ($leadSource) {
            try {
                $leadSource->status = $leadSource->status === 'active' ? 'inactive' : 'active';
                $leadSource->save();

                return redirect()->back()->with('success', __('Lead source status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead source status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead source not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-lead-sources')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $sources = \App\Models\LeadSource::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();

            if ($sources->isEmpty()) {
                return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }

            $inUse = $sources->filter(fn($s) => $s->leads()->count() > 0);
            $deletable = $sources->filter(fn($s) => $s->leads()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to leads.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected lead sources because they are currently assigned to leads.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}