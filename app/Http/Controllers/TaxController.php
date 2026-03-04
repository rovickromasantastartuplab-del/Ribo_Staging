<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $query = Tax::query()
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

        $taxes = $query->paginate($request->per_page ?? 10);

        return Inertia::render('taxes/index', [
            'taxes' => $taxes,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:999.9999',
            'type' => 'nullable|in:percentage,fixed',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['type'] = $validated['type'] ?? 'percentage';
        $validated['type'] = $validated['type'] ?? 'percentage';

        Tax::create($validated);

        return redirect()->back()->with('success', __('Tax created successfully.'));
    }

    public function update(Request $request, $taxId)
    {
        $tax = Tax::where('id', $taxId)
            ->where('created_by', createdBy())
            ->first();

        if ($tax) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'rate' => 'required|numeric|min:0|max:999.9999',
                    'type' => 'nullable|in:percentage,fixed',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);

                $tax->update($validated);

                return redirect()->back()->with('success', __('Tax updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update tax.'));
            }
        } else {
            return redirect()->back()->with('error', __('Tax not found.'));
        }
    }

    public function destroy($taxId)
    {
        $tax = Tax::where('id', $taxId)
            ->where('created_by', createdBy())
            ->first();

        if ($tax) {
            try {
                $tax->delete();
                return redirect()->back()->with('success', __('Tax deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete tax.'));
            }
        } else {
            return redirect()->back()->with('error', __('Tax not found.'));
        }
    }

    public function toggleStatus($taxId)
    {
        $tax = Tax::where('id', $taxId)
            ->where('created_by', createdBy())
            ->first();

        if ($tax) {
            try {
                $tax->status = $tax->status === 'active' ? 'inactive' : 'active';
                $tax->save();

                return redirect()->back()->with('success', __('Tax status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update tax status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Tax not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-taxes')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $query = \App\Models\Tax::whereIn('id', $validated['ids'])->where('created_by', createdBy());
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