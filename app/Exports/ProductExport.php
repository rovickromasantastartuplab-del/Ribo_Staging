<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection, WithHeadings
{
    public function __construct(private Request $request) {}

    public function collection()
    {
        $query = Product::with(['category', 'brand', 'tax', 'assignedUser'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        if ($this->request->filled('category') && $this->request->category !== 'all') {
            $query->where('category_id', $this->request->category);
        }

        if ($this->request->filled('brand') && $this->request->brand !== 'all') {
            $query->where('brand_id', $this->request->brand);
        }

        if ($this->request->filled('status') && $this->request->status !== 'all') {
            $query->where('status', $this->request->status);
        }

        if ($this->request->filled('assigned_to') && $this->request->assigned_to !== 'all') {
            if ($this->request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $this->request->assigned_to);
            }
        }

        return $query->get()
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'tax' => $product->tax?->name,
                    'status' => $product->status,
                    'assigned_user' => $product->assignedUser?->name,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Name',
            'SKU',
            'Description',
            'Price',
            'Stock Quantity',
            'Category',
            'Brand',
            'Tax',
            'Status',
            'Assigned User',
        ];
    }
}