<?php

namespace App\Imports;

use App\Models\WeddingSupplier;
use App\Models\WeddingSupplierCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;

class WeddingSupplierImport implements ToModel, WithHeadingRow, WithEvents
{
    private $addedCount = 0;
    private $skippedCount = 0;

    public function model(array $row)
    {
        // Skip if name is empty
        if (empty($row['name'])) {
            return null;
        }

        // Check for duplicate name (assuming names should be unique for simplicity in import)
        $existingSupplier = WeddingSupplier::where('name', $row['name'])->first();
        if ($existingSupplier) {
            $this->skippedCount++;
            return null;
        }

        // Handle Category
        $categoryId = null;
        if (!empty($row['category'])) {
            $category = WeddingSupplierCategory::firstOrCreate(
                ['name' => trim($row['category'])]
            );
            $categoryId = $category->id;
        } else {
            // Skip if no category provided as it's required
            $this->skippedCount++;
            return null;
        }

        // Create Supplier
        $supplier = WeddingSupplier::create([
            'name' => $row['name'],
            'category_id' => $categoryId,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null, // Mapped 'Phone' to 'phone'
            'telephone' => $row['telephone'] ?? null,
            'website' => $row['website'] ?? null,
            'address' => $row['address'] ?? null,
            'facebook' => $row['facebook'] ?? null,
            'tiktok' => $row['tiktok'] ?? null,
            'available_contact_time' => $row['available_contact_time'] ?? null,
        ]);

        // Add Contact if provided
        if (!empty($row['contact_name'])) {
            $supplier->contacts()->create([
                'name' => $row['contact_name'],
                'position' => $row['contact_position'] ?? null,
                'phone' => $row['contact_phone'] ?? null,
                'email' => $row['contact_email'] ?? null,
            ]);
        }

        $this->addedCount++;
        return $supplier;
    }

    public function registerEvents(): array
    {
        return [];
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
