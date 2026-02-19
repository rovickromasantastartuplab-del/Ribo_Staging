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
}