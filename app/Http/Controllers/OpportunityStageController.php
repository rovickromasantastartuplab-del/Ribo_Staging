<?php

namespace App\Http\Controllers;

use App\Models\OpportunityStage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpportunityStageController extends Controller
{
    public function index(Request $request)
    {
        $query = OpportunityStage::query()
            ->where('created_by', createdBy());

        if (auth()->user()->type !== 'company' && !auth()->user()->can('manage-opportunity-stages')) {
            $query->whereRaw('1 = 0');
        }

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

        $opportunityStages = $query->paginate($request->per_page ?? 10);

        return Inertia::render('opportunity-stages/index', [
            'opportunityStages' => $opportunityStages,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'probability' => 'required|integer|min:0|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        OpportunityStage::create($validated);

        return redirect()->back()->with('success', __('Opportunity stage created successfully.'));
    }

    public function update(Request $request, $opportunityStageId)
    {
        $opportunityStage = OpportunityStage::where('id', $opportunityStageId)
            ->where('created_by', createdBy())
            ->first();

        if ($opportunityStage) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'color' => 'required|string|max:7',
                    'probability' => 'required|integer|min:0|max:100',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $opportunityStage->update($validated);

                return redirect()->back()->with('success', __('Opportunity stage updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update opportunity stage.'));
            }
        } else {
            return redirect()->back()->with('error', __('Opportunity stage not found.'));
        }
    }

    public function destroy($opportunityStageId)
    {
        $opportunityStage = OpportunityStage::where('id', $opportunityStageId)
            ->where('created_by', createdBy())
            ->first();

        if ($opportunityStage) {
            try {
                if ($opportunityStage->opportunities()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete opportunity stage that is currently assigned to one or more opportunities.'));
                }

                $opportunityStage->delete();
                return redirect()->back()->with('success', __('Opportunity stage deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete opportunity stage.'));
            }
        } else {
            return redirect()->back()->with('error', __('Opportunity stage not found.'));
        }
    }

    public function toggleStatus($opportunityStageId)
    {
        $opportunityStage = OpportunityStage::where('id', $opportunityStageId)
            ->where('created_by', createdBy())
            ->first();

        if ($opportunityStage) {
            try {
                $opportunityStage->status = $opportunityStage->status === 'active' ? 'inactive' : 'active';
                $opportunityStage->save();

                return redirect()->back()->with('success', __('Opportunity stage status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update opportunity stage status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Opportunity stage not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-opportunity-stages')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $stages = \App\Models\OpportunityStage::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();

            if ($stages->isEmpty()) {
                return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }

            $inUse = $stages->filter(fn($s) => $s->opportunities()->count() > 0);
            $deletable = $stages->filter(fn($s) => $s->opportunities()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to opportunities.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected opportunity stages because they are currently assigned to opportunities.'));
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
            'items.*.id' => 'required|exists:opportunity_stages,id',
            'items.*.order' => 'required|integer',
        ]);

        try {
            foreach ($validated['items'] as $item) {
                OpportunityStage::where('id', $item['id'])
                    ->where('created_by', createdBy())
                    ->update(['order' => $item['order']]);
            }
            return response()->json(['message' => __('Order updated successfully.')]);
        } catch (\Exception $e) {
            return response()->json(['message' => __('Failed to update order.')], 500);
        }
    }
}