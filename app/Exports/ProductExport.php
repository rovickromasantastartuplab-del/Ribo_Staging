<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $query = Product::with(['category', 'brand', 'tax', 'assignedUser'])
            ->where('created_by', createdBy())
            ->when(!auth()->user()->hasRole('company'), function ($q) {
                $q->where('assigned_to', auth()->id());
            });
        
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