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

        $perPage = $request->input('per_page', 10);
        $suppliers = $query->paginate($perPage)->withQueryString();
        $categories = WeddingSupplierCategory::orderBy('name')->get();

        return Inertia::render('wedding-suppliers/index', [
            'suppliers' => $suppliers,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'per_page']),
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

    /**
     * Export wedding suppliers to Excel
     */
    public function fileExport()
    {
        $this->authorize('viewAny', WeddingSupplier::class);

        $name = 'wedding_suppliers_' . date('Y-m-d_H-i-s');
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\WeddingSupplierExport(), $name . '.xlsx');
    }

    /**
     * Download template for import
     */
    public function downloadTemplate()
    {
        $headers = [
            'Name',
            'Category',
            'Email',
            'Phone',
            'Telephone',
            'Website',
            'Address',
            'Facebook',
            'TikTok',
            'Available Contact Time',
            'Contact Name',
            'Contact Position',
            'Contact Phone',
            'Contact Email'
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=wedding_supplier_import_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    /**
     * Parse uploaded file to preview columns and data
     */
    public function parseFile(Request $request)
    {
        $this->authorize('create', WeddingSupplier::class);

        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx|max:10240',
        ]);

        try {
            $file = $request->file('file');

            // Read headers and preview data
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestRow = $worksheet->getHighestRow();
            $headers = [];

            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $value = $worksheet->getCell($col . '1')->getValue();
                if ($value) {
                    $headers[] = $value;
                }
            }

            // Get full data
            $fullData = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $colIndex = 0;
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    if ($colIndex < count($headers)) {
                        $colValue = $worksheet->getCell($col . $row)->getValue();
                        $rowData[$headers[$colIndex]] = $colValue !== null ? (string) $colValue : '';
                    }
                    $colIndex++;
                }
                // Only add row if it has some data
                if (!empty(array_filter($rowData, fn($value) => $value !== ''))) {
                    $fullData[] = $rowData;
                }
            }

            return response()->json([
                'excelColumns' => $headers,
                'previewData' => $fullData // Return full data so frontend can map and import all rows
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to parse file: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Import wedding suppliers from mapped data
     */
    public function fileImport(Request $request)
    {
        $this->authorize('create', WeddingSupplier::class);

        $request->validate([
            'data' => 'required|array',
        ]);

        try {
            $data = $request->data;

            // Create temporary CSV file from data
            // Use storage/app/temp path which is standard for temporary files in Laravel
            $tempFile = storage_path('app/temp/import_' . time() . '.csv');

            // Ensure tmp directory exists
            if (!file_exists(dirname($tempFile))) {
                mkdir(dirname($tempFile), 0755, true);
            }

            $handle = fopen($tempFile, 'w');

            // Write headers (keys of the first row)
            if (!empty($data) && isset($data[0])) {
                fputcsv($handle, array_keys($data[0]));

                // Write data rows
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
            }
            fclose($handle);

            $import = new \App\Imports\WeddingSupplierImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $tempFile);

            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            $message = __('Import completed: :added suppliers added, :skipped skipped (duplicates/invalid)', [
                'added' => $import->getAddedCount(),
                'skipped' => $import->getSkippedCount()
            ]);

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to import: :error', ['error' => $e->getMessage()]));
        }
    }
}
