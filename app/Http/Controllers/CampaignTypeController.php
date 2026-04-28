<?php

namespace App\Http\Controllers;

use App\Models\CampaignType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = CampaignType::query()
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

        $campaignTypes = $query->paginate($request->per_page ?? 10);

        return Inertia::render('campaign-types/index', [
            'campaignTypes' => $campaignTypes,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        CampaignType::create($validated);

        return redirect()->back()->with('success', __('Campaign type created successfully.'));
    }

    public function update(Request $request, $campaignTypeId)
    {
        $campaignType = CampaignType::where('id', $campaignTypeId)
            ->where('created_by', createdBy())
            ->first();

        if ($campaignType) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'color' => 'nullable|string|max:7',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $campaignType->update($validated);

                return redirect()->back()->with('success', __('Campaign type updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update campaign type.'));
            }
        } else {
            return redirect()->back()->with('error', __('Campaign type not found.'));
        }
    }

    public function destroy($campaignTypeId)
    {
        $campaignType = CampaignType::where('id', $campaignTypeId)
            ->where('created_by', createdBy())
            ->first();

        if ($campaignType) {
            try {
                if ($campaignType->campaigns()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete campaign type that is currently assigned to one or more campaigns.'));
                }
                
                $campaignType->delete();
                return redirect()->back()->with('success', __('Campaign type deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete campaign type.'));
            }
        } else {
            return redirect()->back()->with('error', __('Campaign type not found.'));
        }
    }

    public function toggleStatus($campaignTypeId)
    {
        $campaignType = CampaignType::where('id', $campaignTypeId)
            ->where('created_by', createdBy())
            ->first();

        if ($campaignType) {
            try {
                $campaignType->status = $campaignType->status === 'active' ? 'inactive' : 'active';
                $campaignType->save();

                return redirect()->back()->with('success', __('Campaign type status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update campaign type status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Campaign type not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-campaign-types')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $campaignTypes = CampaignType::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();

            if ($campaignTypes->isEmpty()) {
                return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }

            $inUse = $campaignTypes->filter(fn($ct) => $ct->campaigns()->count() > 0);
            $deletable = $campaignTypes->filter(fn($ct) => $ct->campaigns()->count() === 0);

            $deletable->each(function($campaignType) {
                $campaignType->delete();
            });

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to campaigns.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected campaign types because they are currently assigned to campaigns.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}