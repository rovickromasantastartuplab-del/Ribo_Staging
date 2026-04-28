<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()
            ->where('created_by', createdBy());

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%');
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

        $categories = $query->paginate($request->per_page ?? 10);

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'filters' => $request->all(['search', 'status', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        Category::create($validated);

        return redirect()->back()->with('success', __('Category created successfully.'));
    }

    public function update(Request $request, $categoryId)
    {
        $category = Category::where('id', $categoryId)
            ->where('created_by', createdBy())
            ->first();

        if ($category) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'slug' => 'nullable|string|max:255',
                    'description' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive',
                ]);

                if (isset($validated['status']) && $validated['status'] === 'inactive' && $category->status === 'active') {
                    if ($category->products()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate category that is currently assigned to one or more products.'));
                    }
                }

                $category->update($validated);

                return redirect()->back()->with('success', __('Category updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update category.'));
            }
        } else {
            return redirect()->back()->with('error', __('Category not found.'));
        }
    }

    public function destroy($categoryId)
    {
        $category = Category::where('id', $categoryId)
            ->where('created_by', createdBy())
            ->first();

        if ($category) {
            try {
                if ($category->products()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete category that is currently assigned to one or more products.'));
                }

                $category->delete();
                return redirect()->back()->with('success', __('Category deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete category.'));
            }
        } else {
            return redirect()->back()->with('error', __('Category not found.'));
        }
    }

    public function toggleStatus($categoryId)
    {
        $category = Category::where('id', $categoryId)
            ->where('created_by', createdBy())
            ->first();

        if ($category) {
            try {
                if ($category->status === 'active') {
                    if ($category->products()->count() > 0) {
                        return redirect()->back()->with('error', __('Cannot deactivate category that is currently assigned to one or more products.'));
                    }
                    $category->status = 'inactive';
                } else {
                    $category->status = 'active';
                }
                $category->save();

                return redirect()->back()->with('success', __('Category status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update category status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Category not found.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-categories')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $categories = \App\Models\Category::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();
            
            if ($categories->isEmpty()) {
                 return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }
            
            $inUse = $categories->filter(fn($c) => $c->products()->count() > 0);
            $deletable = $categories->filter(fn($c) => $c->products()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to products.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected categories because they are currently assigned to products.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}