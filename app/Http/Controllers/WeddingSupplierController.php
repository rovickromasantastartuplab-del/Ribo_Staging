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
                'delete_supplier' => auth()->user()->can('delete', new WeddingSupplier),
                'export_supplier' => auth()->user()->isSuperAdmin(),
                'import_supplier' => auth()->user()->isSuperAdmin(),
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
            'website' => 'nullable|string|max:255',
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
            'website' => 'nullable|string|max:255',
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
        $this->authorize('create', WeddingSupplier::class);

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
            'file' => 'required|mimes:csv,txt,xls,xlsx|max:51200',
        ]);

        try {
            $file = $request->file('file');

            // Store file server-side
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempFileName = 'import_supplier_' . auth()->id() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($tempDir, $tempFileName);
            $storedFilePath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;

            // Read only headers and a small preview (3 rows)
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestRow = $worksheet->getHighestRow();

            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headers = [];
            $headerMap = [];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $value = $worksheet->getCell($colLetter . '1')->getValue();

                if ($value !== null && $value !== '') {
                    $strValue = trim((string) $value);
                    $headers[] = $strValue;
                    $headerMap[$colLetter] = $strValue;
                }
            }

            $previewData = [];
            $previewLimit = min($highestRow, 4);
            for ($row = 2; $row <= $previewLimit; $row++) {
                $rowData = [];
                foreach ($headerMap as $colLetter => $headerName) {
                    $colValue = $worksheet->getCell($colLetter . $row)->getValue();
                    $rowData[$headerName] = $colValue !== null ? trim((string) $colValue) : '';
                }
                if (!empty(array_filter($rowData, fn($value) => $value !== ''))) {
                    $previewData[] = $rowData;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json([
                'excelColumns' => array_values($headers),
                'previewData' => $previewData,
                'tempFile' => $tempFileName,
                'totalRows' => max(0, $highestRow - 1),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => __('Failed to parse file: :error', ['error' => $e->getMessage()])], 500);
        }
    }

    /**
     * Import wedding suppliers from mapped data
     */
    public function fileImport(Request $request)
    {
        $this->authorize('create', WeddingSupplier::class);

        $request->validate([
            'mapping' => 'required|array',
            'tempFile' => 'required|string',
        ]);

        try {
            set_time_limit(600);
            ini_set('memory_limit', '512M');

            $tempFileName = basename($request->tempFile);
            $storedFilePath = storage_path('app/temp/' . $tempFileName);

            if (!file_exists($storedFilePath)) {
                return redirect()->back()->with('error', __('Import file not found. Please re-upload.'));
            }

            $mapping = $request->mapping;

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestRow = $worksheet->getHighestRow();

            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $fileHeaders = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $value = $worksheet->getCell($colLetter . '1')->getValue();
                if ($value !== null && $value !== '') {
                    $fileHeaders[$colLetter] = trim((string) $value);
                }
            }

            $mappedTempFile = storage_path('app/temp/mapped_' . time() . '_' . auth()->id() . '.csv');
            $handle = fopen($mappedTempFile, 'w');

            $dbFields = array_keys($mapping);
            fputcsv($handle, $dbFields);

            $excelColToLetter = array_flip($fileHeaders);

            for ($row = 2; $row <= $highestRow; $row++) {
                $mappedRow = [];
                $hasData = false;

                foreach ($mapping as $dbField => $excelColumn) {
                    $colLetter = $excelColToLetter[$excelColumn] ?? null;
                    $value = '';
                    if ($colLetter) {
                        $cellValue = $worksheet->getCell($colLetter . $row)->getValue();
                        $value = $cellValue !== null ? trim((string) $cellValue) : '';
                    }
                    $mappedRow[] = $value;
                    if ($value !== '') {
                        $hasData = true;
                    }
                }

                if ($hasData) {
                    fputcsv($handle, $mappedRow);
                }
            }
            fclose($handle);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $import = new \App\Imports\WeddingSupplierImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $mappedTempFile);

            if (file_exists($mappedTempFile)) {
                unlink($mappedTempFile);
            }
            if (file_exists($storedFilePath)) {
                unlink($storedFilePath);
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
