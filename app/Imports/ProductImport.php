<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToModel, WithHeadingRow
{
    use Importable;

    private $addedCount = 0;
    private $skippedCount = 0;

    public function model(array $row)
    {
        // Skip if both name and sku are empty
        if (empty($row['name']) && empty($row['sku'])) {
            return null;
        }

        // Check for duplicate SKU
        if (!empty($row['sku'])) {
            $productBySku = Product::where('sku', $row['sku'])->where('created_by', createdBy())->first();
            if ($productBySku) {
                $this->skippedCount++;
                return null;
            }
        }

        $productData = [
            'name' => $row['name'] ?? '',
            'sku' => $row['sku'] ?? '',
            'description' => $row['description'] ?? '',
            'price' => is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0,
            'stock_quantity' => is_numeric($row['stock'] ?? null) ? (int) $row['stock'] : 0,
            'status' => in_array($row['status'] ?? 'active', ['active', 'inactive']) ? $row['status'] : 'active',
            'created_by' => createdBy(),
        ];
    
        // Category
        $categoryValue = trim($row['category'] ?? '');
        if (!empty($categoryValue)) {
            $category = \App\Models\Category::firstOrCreate(
                ['name' => $categoryValue, 'created_by' => createdBy()],
                ['status' => 'active']
            );
            $productData['category_id'] = $category->id;
        }

        // Brand
        $brandValue = trim($row['brand'] ?? '');
        if (!empty($brandValue)) {
            $brand = \App\Models\Brand::firstOrCreate(
                ['name' => $brandValue, 'created_by' => createdBy()],
                ['status' => 'active']
            );
            $productData['brand_id'] = $brand->id;
        }

        // Tax
        $taxValue = trim($row['tax'] ?? '');
        if (!empty($taxValue)) {
            $tax = \App\Models\Tax::firstOrCreate(
                ['name' => $taxValue, 'created_by' => createdBy()],
                ['rate' => 0, 'status' => 'active']
            );
            $productData['tax_id'] = $tax->id;
        } else {
            $productData['tax_id'] = \App\Models\Tax::where('created_by', createdBy())->value('id');
        }

        if (!auth()->user()->hasRole('company')) {
            $productData['assigned_to'] = auth()->id();
        }

        $this->addedCount++;
        return new Product($productData);
    }

    public function getAddedCount()
    {
        return $this->addedCount;
    }

    public function getSkippedCount()
    {
        return $this->skippedCount;
    }
}
