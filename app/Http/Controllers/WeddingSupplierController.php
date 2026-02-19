<?php

namespace App\Http\Controllers;

use App\Models\WeddingSupplier;
use App\Models\WeddingSupplierCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class WeddingSupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', WeddingSupplier::class);

        $query = WeddingSupplier::query()
            ->with(['category', 'contacts']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by Category
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        $query->orderBy('name');

        $suppliers = $query->paginate(10)->withQueryString();
        $categories = WeddingSupplierCategory::orderBy('name')->get();

        return Inertia::render('wedding-suppliers/index', [
            'suppliers' => $suppliers,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id']),
            'can' => [
                'create_supplier' => auth()->user()->can('create', WeddingSupplier::class),
                'edit_supplier' => auth()->user()->can('update', new WeddingSupplier),
                'manage_categories' => auth()->user()->can('create', WeddingSupplierCategory::class),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', WeddingSupplier::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:wedding_supplier_categories,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'telephone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'facebook' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'available_contact_time' => 'nullable|string|max:255',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.email' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $supplier = WeddingSupplier::create($validated);

            if (!empty($validated['contacts'])) {
                $supplier->contacts()->createMany($validated['contacts']);
            }
        });

        return back()->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(WeddingSupplier $weddingSupplier)
    {
        $this->authorize('view', $weddingSupplier);

        $weddingSupplier->load(['category', 'contacts']);

        return Inertia::render('wedding-suppliers/show', [
            'supplier' => $weddingSupplier
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WeddingSupplier $weddingSupplier)
    {
        $this->authorize('update', $weddingSupplier);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:wedding_supplier_categories,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'telephone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'facebook' => 'nullable|string|max:255',
            'tiktok' => 'nullable|string|max:255',
            'available_contact_time' => 'nullable|string|max:255',
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|integer|exists:wedding_supplier_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.email' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated, $weddingSupplier) {
            $weddingSupplier->update($validated);

            // Sync contacts
            if (isset($validated['contacts'])) {
                // Get IDs of contacts to keep
                $keepIds = array_filter(array_column($validated['contacts'], 'id'));

                // Delete contacts not in the list
                $weddingSupplier->contacts()->whereNotIn('id', $keepIds)->delete();

                foreach ($validated['contacts'] as $contactData) {
                    if (isset($contactData['id'])) {
                        $weddingSupplier->contacts()->where('id', $contactData['id'])->update($contactData);
                    } else {
                        $weddingSupplier->contacts()->create($contactData);
                    }
                }
            } else {
                // If contacts key is present but empty, delete all? Or if not present, ignore?
                // Assuming if sent as empty array, delete all.
                if (array_key_exists('contacts', $validated)) {
                    $weddingSupplier->contacts()->delete();
                }
            }
        });

        return back()->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WeddingSupplier $weddingSupplier)
    {
        $this->authorize('delete', $weddingSupplier);

        $weddingSupplier->delete();

        return back()->with('success', 'Supplier deleted successfully.');
    }
}
