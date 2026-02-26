<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeddingSupplierCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Venue & Catering
            'Event Venue',
            'Hotel & Resort',
            'Beach & Garden Venue',
            'Restaurant & Dining',
            'Catering Services',
            'Food Cart & Booths',
            'Cake & Desserts',
            'Cocktail & Bar Services',

            // Photography & Videography
            'Photography',
            'Videography',
            'Photo & Video',
            'Photo Booth',
            'Drone Services',

            // Entertainment & Music
            'Band & Live Music',
            'DJ Services',
            'Host & Emcee',
            'Cultural Performers',
            'Fireworks & Pyrotech',
            'Lights & Sounds',

            // Styling & Design
            'Floral & Styling',
            'Wedding Coordinator',
            'Bridal Gown & Attire',
            'Groom Suit & Barong',
            'Hair & Makeup',
            'Invitation & Stationery',
            'Wedding Favors & Souvenirs',

            // Transportation
            'Bridal Car',
            'Transportation Services',

            // Digital & Media
            'Media Partners',
            'Live Streaming',
            'Social Media Coverage',

            // Rings & Jewelry
            'Rings & Jewelry',

            // Other
            'Coffee Bar',
            'Other',
        ];

        foreach ($categories as $name) {
            DB::table('wedding_supplier_categories')->insertOrIgnore([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
