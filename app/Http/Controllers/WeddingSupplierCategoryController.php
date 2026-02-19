<?php

namespace App\Http\Controllers;

use App\Models\WeddingSupplierCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeddingSupplierCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', WeddingSupplierCategory::class);

        $categories = WeddingSupplierCategory::query()
            ->orderBy('name')
            ->get();

        return response()->json($categories);
        // If we need a dedicated page for categories, we can return Inertia::render for admins.
        // For now, index is mostly used for dropdowns or admin management via modal? 
        // Let's assume admin sidebar has "Supplier Categories" or it's part of the main page.
        // If it's a separate page:
        // return Inertia::render('WeddingSupplierCategories/Index', [
        //     'categories' => $categories
        // ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', WeddingSupplierCategory::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:wedding_supplier_categories',
        ]);

        WeddingSupplierCategory::create($validated);

        return back()->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WeddingSupplierCategory $weddingSupplierCategory)
    {
        $this->authorize('update', $weddingSupplierCategory);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:wedding_supplier_categories,name,' . $weddingSupplierCategory->id,
        ]);

        $weddingSupplierCategory->update($validated);

        return back()->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WeddingSupplierCategory $weddingSupplierCategory)
    {
        $this->authorize('delete', $weddingSupplierCategory);

        $weddingSupplierCategory->delete();

        return back()->with('success', 'Category deleted successfully.');
    }
}
