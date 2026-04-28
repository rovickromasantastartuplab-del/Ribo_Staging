<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::query()
            ->where('created_by', createdBy());

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('website', 'like', '%' . $request->search . '%');
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

        $brands = $query->paginate($request->per_page ?? 10);

        return Inertia::render('brands/index', [
            'brands' => $brands,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        Brand::create($validated);

        return redirect()->back()->with('success', __('Brand created successfully.'));
    }

    public function update(Request $request, $brandId)
    {
        $brand = Brand::where('id', $brandId)
            ->where('created_by', createdBy())
            ->first();

        if ($brand) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'website' => 'nullable|url|max:255',
                    'status' => 'nullable|in:active,inactive',
                ]);

                if (isset($validated['status']) && $validated['status'] === 'inactive' && $brand->status === 'active') {
                    if ($brand->products()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate brand that is currently assigned to one or more products.'));
                    }
                }

                $brand->update($validated);

                return redirect()->back()->with('success', __('Brand updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update brand.'));
            }
        } else {
            return redirect()->back()->with('error', __('Brand not found.'));
        }
    }

    public function destroy($brandId)
    {
        $brand = Brand::where('id', $brandId)
            ->where('created_by', createdBy())
            ->first();

        if ($brand) {
            try {
                if ($brand->products()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete brand that is currently assigned to one or more products.'));
                }

                $brand->delete();
                return redirect()->back()->with('success', __('Brand deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete brand.'));
            }
        } else {
            return redirect()->back()->with('error', __('Brand not found.'));
        }
    }

    public function toggleStatus($brandId)
    {
        $brand = Brand::where('id', $brandId)
            ->where('created_by', createdBy())
            ->first();

        if ($brand) {
            try {
                if ($brand->status === 'active') {
                    if ($brand->products()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate brand that is currently assigned to one or more products.'));
                    }
                    $brand->status = 'inactive';
                } else {
                    $brand->status = 'active';
                }
                $brand->save();

                return redirect()->back()->with('success', __('Brand status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update brand status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Brand not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-brands')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $brands = \App\Models\Brand::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();
            
            if ($brands->isEmpty()) {
                 return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }
            
            $inUse = $brands->filter(fn($b) => $b->products()->count() > 0);
            $deletable = $brands->filter(fn($b) => $b->products()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to products.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected brands because they are currently assigned to products.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}