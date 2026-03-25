<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tax;
use App\Exports\ProductExport;
use App\Imports\ProductImport;
use App\Services\StorageConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'brand', 'tax', 'assignedUser', 'media'])
            ->where(function ($q) {
                if (auth()->user()->type === 'company') {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('sku', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle category filter
        if ($request->has('category') && !empty($request->category) && $request->category !== 'all') {
            $query->where('category_id', $request->category);
        }

        // Handle brand filter
        if ($request->has('brand') && !empty($request->brand) && $request->brand !== 'all') {
            $query->where('brand_id', $request->brand);
        }

        // Handle status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Handle assigned_to filter
        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $products = $query->paginate($request->per_page ?? 10);

        // Get dropdown data
        $categories = Category::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $brands = Brand::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $taxes = Tax::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name', 'rate']);

        // Get users for assignment dropdown (only for company users)
        $users = [];
        if (auth()->user()->type === 'company' || auth()->user()->type === 'staff') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->where('type', '!=', 'company')
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'taxes' => $taxes,
            'users' => $users,
            'samplePath' => route('product.download.template'),
            'filters' => $request->all(['search', 'category', 'brand', 'status', 'assigned_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function create()
    {
        $categories = Category::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $brands = Brand::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $taxes = Tax::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name', 'rate']);

        $users = [];
        if (auth()->user()->type === 'company' || auth()->user()->type === 'staff') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->where('type', '!=', 'company')
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('products/create', [
            'categories' => $categories,
            'brands' => $brands,
            'taxes' => $taxes,
            'users' => $users
        ]);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'brand', 'tax', 'assignedUser', 'creator', 'media'])
            ->where('created_by', createdBy())
            ->findOrFail($id);

        return Inertia::render('products/show', [
            'product' => $product,
            'mainImage' => $product->main_image_url,
            'additionalImages' => $product->additional_image_urls
        ]);
    }

    public function edit($id)
    {
        $product = Product::with(['category', 'brand', 'tax', 'assignedUser', 'creator', 'media'])
            ->where('created_by', createdBy())
            ->findOrFail($id);

        $categories = Category::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $brands = Brand::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name']);

        $taxes = Tax::where('created_by', createdBy())
            ->where('status', 'active')
            ->get(['id', 'name', 'rate']);

        $users = [];
        if (auth()->user()->type === 'company' || auth()->user()->type === 'staff') {
            $users = \App\Models\User::where('created_by', createdBy())
                ->where('type', '!=', 'company')
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('products/edit', [
            'product' => array_merge($product->toArray(), [
                'main_image_id' => $product->main_image_id,
                'additional_image_ids' => $product->additional_image_ids ?: []
            ]),
            'categories' => $categories,
            'brands' => $brands,
            'taxes' => $taxes,
            'users' => $users,
            'mainImage' => $product->main_image_url,
            'additionalImages' => $product->additional_image_urls
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:255|unique:products,sku',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stock_quantity' => 'nullable|integer|min:0',
                'image' => 'nullable|string',
                'main_image_id' => 'nullable|exists:media,id',
                'additional_image_ids' => 'nullable|array',
                'additional_image_ids.*' => 'exists:media,id',
                'category_id' => 'nullable|exists:categories,id',
                'brand_id' => 'nullable|exists:brands,id',
                'tax_id' => 'nullable|exists:taxes,id',
                'status' => 'nullable|in:active,inactive',
                'assigned_to' => 'nullable|exists:users,id',
            ]);

            $validated['created_by'] = createdBy();
            $validated['status'] = $validated['status'] ?? 'active';
            $validated['stock_quantity'] = $validated['stock_quantity'] ?? 0;

            if (auth()->user()->type != 'company' && auth()->user()->type != 'staff') {
                $validated['assigned_to'] = auth()->id();
            }

            $product = Product::create($validated);

            return redirect()->route('products.index')->with('success', __('Product created successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to create product: :error', ['error' => $e->getMessage()]));
        }
    }

    public function update(Request $request, $productId)
    {
        $product = Product::where('id', $productId)
            ->where('created_by', createdBy())
            ->first();

        if ($product) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'sku' => 'required|string|max:255|unique:products,sku,' . $productId,
                    'description' => 'nullable|string',
                    'price' => 'required|numeric|min:0',
                    'stock_quantity' => 'nullable|integer|min:0',
                    'image' => 'nullable|string',
                    'main_image_id' => 'nullable|exists:media,id',
                    'additional_image_ids' => 'nullable|array',
                    'additional_image_ids.*' => 'exists:media,id',
                    'category_id' => 'nullable|exists:categories,id',
                    'brand_id' => 'nullable|exists:brands,id',
                    'tax_id' => 'nullable|exists:taxes,id',
                    'status' => 'nullable|in:active,inactive',
                    'assigned_to' => 'nullable|exists:users,id',
                ]);

                if (auth()->user()->type != 'company' && auth()->user()->type != 'staff') {
                    $validated['assigned_to'] = auth()->id();
                }

                $product->update($validated);

                return redirect()->back()->with('success', __('Product updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update product.'));
            }
        } else {
            return redirect()->back()->with('error', __('Product not found.'));
        }
    }

    public function destroy($productId)
    {
        $product = Product::where('id', $productId)
            ->where('created_by', createdBy())
            ->first();

        if ($product) {
            try {
                if ($product->opportunities()->count() > 0) {
                    return redirect()->back()->with('error', __('Cannot delete product that is currently assigned to one or more opportunities.'));
                }

                $product->delete();
                return redirect()->back()->with('success', __('Product deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete product.'));
            }
        } else {
            return redirect()->back()->with('error', __('Product not found.'));
        }
    }

    public function toggleStatus($productId)
    {
        $product = Product::where('id', $productId)
            ->where('created_by', createdBy())
            ->first();

        if ($product) {
            try {
                $product->status = $product->status === 'active' ? 'inactive' : 'active';
                $product->save();

                return redirect()->back()->with('success', __('Product status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update product status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Product not found.'));
        }
    }

    public function fileExport(Request $request)
    {
        if (!auth()->user()->can('export-products')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $name = 'product_' . date('Y-m-d_H-i-s');
        return Excel::download(new ProductExport($request), $name . '.xlsx');
    }

    public function downloadTemplate()
    {
        if (!auth()->user()->can('import-products')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $headers = [
            'name',
            'sku',
            'description',
            'price',
            'stock',
            'category',
            'brand',
            'tax',
            'status'
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=sample-product.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    public function parseFile(Request $request)
    {
        if (!auth()->user()->can('import-products')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx|max:65536',
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
            $tempFileName = 'import_' . auth()->id() . '_' . time() . '_' . uniqid() . '.' . $extension;
            $file->move($importDir, $tempFileName);
            $storedFilePath = $importDir . '/' . $tempFileName;

            $headers = [];
            $previewData = [];

            if (in_array($extension, ['csv', 'txt'])) {
                $handle = fopen($storedFilePath, 'r');
                if ($handle === false) {
                    throw new \Exception('Could not open file');
                }
                $headerRow = fgetcsv($handle);
                if ($headerRow === false) {
                    fclose($handle);
                    throw new \Exception('File is empty or invalid');
                }
                $headers = array_map('trim', $headerRow);
                if (!empty($headers[0])) {
                    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                }
                $headers = array_filter($headers, fn($h) => $h !== '');

                $previewCount = 0;
                while (($row = fgetcsv($handle)) !== false && $previewCount < 3) {
                    $rowData = [];
                    foreach ($headers as $idx => $headerName) {
                        $rowData[$headerName] = isset($row[$idx]) ? trim($row[$idx]) : '';
                    }
                    if (!empty(array_filter($rowData, fn($v) => $v !== ''))) {
                        $previewData[] = $rowData;
                        $previewCount++;
                    }
                }
                fclose($handle);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

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

                $highestRow = min($worksheet->getHighestRow(), 5);
                for ($row = 2; $row <= $highestRow && count($previewData) < 3; $row++) {
                    $rowData = [];
                    foreach ($headerMap as $colLetter => $headerName) {
                        $colValue = $worksheet->getCell($colLetter . $row)->getValue();
                        $rowData[$headerName] = $colValue !== null ? trim((string) $colValue) : '';
                    }
                    if (!empty(array_filter($rowData, fn($v) => $v !== ''))) {
                        $previewData[] = $rowData;
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            return response()->json([
                'excelColumns' => array_values($headers),
                'previewData'  => $previewData,
                'tempFile'     => $tempFileName,
            ]);
        } catch (\Throwable $e) {
            if (isset($storedFilePath) && file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            return response()->json(['error' => __('Failed to parse file: :error', ['error' => $e->getMessage()])], 500);
        }
    }

    public function fileImport(Request $request)
    {
        if (!auth()->user()->can('import-products')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $rules = [
            'mapping'  => 'required|array',
            'tempFile' => 'required|string',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        try {
            ini_set('max_execution_time', '600');
            set_time_limit(600);

            $mapping      = $request->mapping;
            $tempFileName = basename($request->tempFile);
            $storedFilePath = storage_path('app/imports/' . $tempFileName);

            if (!file_exists($storedFilePath)) {
                return redirect()->back()->with('error', __('Import file not found. Please re-upload the file.'));
            }

            $extension = strtolower(pathinfo($storedFilePath, PATHINFO_EXTENSION));

            // Build a mapped CSV that ProductImport can consume
            $mappedFile = storage_path('app/imports/mapped_' . time() . '_' . uniqid() . '.csv');
            $outHandle  = fopen($mappedFile, 'w');

            // Write DB field names as the header row
            fputcsv($outHandle, array_keys($mapping));

            if (in_array($extension, ['csv', 'txt'])) {
                $inHandle  = fopen($storedFilePath, 'r');
                $headerRow = fgetcsv($inHandle);
                if (!empty($headerRow[0])) {
                    $headerRow[0] = preg_replace('/^\x{FEFF}/u', '', $headerRow[0]);
                }
                $headerRow   = array_map('trim', $headerRow);
                $colIndexMap = array_flip($headerRow);

                while (($row = fgetcsv($inHandle)) !== false) {
                    $mappedRow = [];
                    foreach ($mapping as $dbField => $excelColumn) {
                        $colIdx      = $colIndexMap[$excelColumn] ?? null;
                        $mappedRow[] = ($colIdx !== null && isset($row[$colIdx])) ? trim($row[$colIdx]) : '';
                    }
                    if (!empty(array_filter($mappedRow, fn($v) => $v !== ''))) {
                        fputcsv($outHandle, $mappedRow);
                    }
                }
                fclose($inHandle);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
                $worksheet   = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestRow    = $worksheet->getHighestRow();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                $headerMap = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $value     = $worksheet->getCell($colLetter . '1')->getValue();
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

            $import = new ProductImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $mappedFile);

            if (file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            if (file_exists($mappedFile)) {
                unlink($mappedFile);
            }

            $message = __('Import completed: :added products added, :skipped products skipped', [
                'added'   => $import->getAddedCount(),
                'skipped' => $import->getSkippedCount(),
            ]);

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if (isset($storedFilePath) && file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            if (isset($mappedFile) && file_exists($mappedFile)) {
                unlink($mappedFile);
            }
            return redirect()->back()->with('error', __('Failed to import: :error', ['error' => $e->getMessage()]));
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-products')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            $products = \App\Models\Product::whereIn('id', $validated['ids'])->where('created_by', createdBy())->get();
            
            if ($products->isEmpty()) {
                 return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }
            
            $inUse = $products->filter(fn($p) => $p->opportunities()->count() > 0);
            $deletable = $products->filter(fn($p) => $p->opportunities()->count() === 0);

            $deletable->each->delete();

            if ($inUse->isNotEmpty() && $deletable->isNotEmpty()) {
                return redirect()->back()->with('warning', __(':deleted record(s) deleted. :skipped record(s) skipped because they are assigned to opportunities.', ['deleted' => $deletable->count(), 'skipped' => $inUse->count()]));
            } elseif ($inUse->isNotEmpty() && $deletable->isEmpty()) {
                return redirect()->back()->with('error', __('Cannot delete the selected products because they are currently assigned to opportunities.'));
            }

            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $deletable->count()]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}
