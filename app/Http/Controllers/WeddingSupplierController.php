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

        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:wedding_supplier_categories,id',
                'email' => 'nullable|email:filter|max:255',
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
                'contacts.*.email' => 'nullable|email:filter|max:255',
            ],
            [
                'contacts.*.name.required' => __('The contact name field is required.'),
                'contacts.*.email.email' => __('The contact email must be a valid email address.'),
            ]
        );

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

        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:wedding_supplier_categories,id',
                'email' => 'nullable|email:filter|max:255',
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
                'contacts.*.email' => 'nullable|email:filter|max:255',
            ],
            [
                'contacts.*.name.required' => __('The contact name field is required.'),
                'contacts.*.email.email' => __('The contact email must be a valid email address.'),
            ]
        );

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
    public function fileExport(Request $request)
    {
        $this->authorize('create', WeddingSupplier::class);

        $name = 'wedding_suppliers_' . date('Y-m-d_H-i-s');
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\WeddingSupplierExport($request), $name . '.xlsx');
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
            ini_set('max_execution_time', '300');
            set_time_limit(300);

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            // Store the file temporarily for later import
            $importDir = storage_path('app/imports');
            if (!file_exists($importDir)) {
                mkdir($importDir, 0755, true);
            }
            $tempFileName = 'supplier_import_' . auth()->id() . '_' . time() . '_' . uniqid() . '.' . $extension;
            $file->move($importDir, $tempFileName);
            $storedFilePath = $importDir . '/' . $tempFileName;

            $headers = [];
            $previewData = [];

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headerMap = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $value = $worksheet->getCell($colLetter . '1')->getCalculatedValue();

                if ($value !== null && $value !== '') {
                    $strValue = trim((string) $value);
                    $headers[] = $strValue;
                    $headerMap[$colLetter] = $strValue;
                }
            }

            // Get preview data (first 3 data rows)
            $highestRow = $worksheet->getHighestRow();
            $previewCount = 0;
            for ($row = 2; $row <= $highestRow && $previewCount < 3; $row++) {
                $rowData = [];
                foreach ($headerMap as $colLetter => $headerName) {
                    $colValue = $worksheet->getCell($colLetter . $row)->getCalculatedValue();
                    $rowData[$headerName] = $colValue !== null ? trim((string) $colValue) : '';
                }
                
                if (!empty(array_filter($rowData, fn($value) => $value !== ''))) {
                    $previewData[] = $rowData;
                    $previewCount++;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->json([
                'excelColumns' => array_values($headers),
                'previewData' => $previewData,
                'tempFile' => $tempFileName
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
            ini_set('max_execution_time', '600');
            set_time_limit(600);

            $mapping = $request->mapping;
            $tempFileName = basename($request->tempFile);
            $storedFilePath = storage_path('app/imports/' . $tempFileName);

            if (!file_exists($storedFilePath)) {
                return redirect()->back()->with('error', __('Import file not found. Please re-upload the file.'));
            }

            $extension = strtolower(pathinfo($storedFilePath, PATHINFO_EXTENSION));

            // Build a mapped CSV that WeddingSupplierImport can consume
            $mappedFile = storage_path('app/imports/mapped_suppliers_' . time() . '_' . uniqid() . '.csv');
            $outHandle = fopen($mappedFile, 'w');

            // Write DB field names as the header row
            fputcsv($outHandle, array_keys($mapping));

            if (in_array($extension, ['csv', 'txt'])) {
                $inHandle = fopen($storedFilePath, 'r');
                $headerRow = fgetcsv($inHandle);
                if (!empty($headerRow[0])) {
                    $headerRow[0] = preg_replace('/^\x{FEFF}/u', '', $headerRow[0]);
                }
                $headerRow = array_map('trim', $headerRow);
                $colIndexMap = array_flip($headerRow);

                while (($row = fgetcsv($inHandle)) !== false) {
                    $mappedRow = [];
                    foreach ($mapping as $dbField => $excelColumn) {
                        $colIdx = $colIndexMap[$excelColumn] ?? null;
                        $mappedRow[] = ($colIdx !== null && isset($row[$colIdx])) ? trim($row[$colIdx]) : '';
                    }
                    if (!empty(array_filter($mappedRow, fn($v) => $v !== ''))) {
                        fputcsv($outHandle, $mappedRow);
                    }
                }
                fclose($inHandle);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestRow = $worksheet->getHighestRow();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                $headerMap = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $value = $worksheet->getCell($colLetter . '1')->getCalculatedValue();
                    if ($value !== null && $value !== '') {
                        $headerMap[trim((string) $value)] = $colLetter;
                    }
                }

                for ($row = 2; $row <= $highestRow; $row++) {
                    $mappedRow = [];
                    foreach ($mapping as $dbField => $excelColumn) {
                        $colLetter = $headerMap[$excelColumn] ?? null;
                        if ($colLetter) {
                            $cellValue = $worksheet->getCell($colLetter . $row)->getCalculatedValue();
                            $mappedRow[] = $cellValue !== null ? trim((string) $cellValue) : '';
                        } else {
                            $mappedRow[] = '';
                        }
                    }
                    if (!empty(array_filter($mappedRow, fn($v) => $v !== ''))) {
                        fputcsv($outHandle, $mappedRow);
                    }
                }
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
            fclose($outHandle);

            $import = new \App\Imports\WeddingSupplierImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $mappedFile);

            // Clean up files
            if (file_exists($storedFilePath)) unlink($storedFilePath);
            if (file_exists($mappedFile)) unlink($mappedFile);

            $message = __('Import completed: :added suppliers added, :skipped skipped (duplicates/invalid)', [
                'added' => $import->getAddedCount(),
                'skipped' => $import->getSkippedCount()
            ]);

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to import: :error', ['error' => $e->getMessage()]));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-wedding-suppliers')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $query = \App\Models\WeddingSupplier::whereIn('id', $validated['ids']);
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
