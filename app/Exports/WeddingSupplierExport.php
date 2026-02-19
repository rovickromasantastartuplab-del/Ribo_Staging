<?php

namespace App\Exports;

use App\Models\WeddingSupplier;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WeddingSupplierExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return WeddingSupplier::with(['category', 'contacts'])->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
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
            'Contact Email',
        ];
    }

    /**
     * @param mixed $supplier
     * @return array
     */
    public function map($supplier): array
    {
        // Get the first contact if available
        $contact = $supplier->contacts->first();

        return [
            $supplier->name,
            $supplier->category ? $supplier->category->name : '',
            $supplier->email,
            $supplier->phone,
            $supplier->telephone,
            $supplier->website,
            $supplier->address,
            $supplier->facebook,
            $supplier->tiktok,
            $supplier->available_contact_time,
            $contact ? $contact->name : '',
            $contact ? $contact->position : '',
            $contact ? $contact->phone : '',
            $contact ? $contact->email : '',
        ];
    }
}
