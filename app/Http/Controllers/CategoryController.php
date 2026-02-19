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
                $category->status = $category->status === 'active' ? 'inactive' : 'active';
                $category->save();

                return redirect()->back()->with('success', __('Category status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update category status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Category not found.'));
        }
    }
}