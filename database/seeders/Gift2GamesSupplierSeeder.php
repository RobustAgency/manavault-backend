<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class Gift2GamesSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Gift2Games',
                'slug' => 'gift2games',
                'type' => 'external',
                'contact_email' => 'support@gift2games.com',
                'contact_phone' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gift2Games - EUR',
                'slug' => 'gift-2-games-eur',
                'type' => 'external',
                'contact_email' => 'support@gift2games.com',
                'contact_phone' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gift2Games - GBP',
                'slug' => 'gift-2-games-gbp',
                'type' => 'external',
                'contact_email' => 'support@gift2games.com',
                'contact_phone' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(
                ['slug' => $supplier['slug']],
                [
                    'name' => $supplier['name'],
                    'type' => $supplier['type'],
                    'contact_email' => $supplier['contact_email'],
                    'contact_phone' => $supplier['contact_phone'],
                    'status' => $supplier['status'],
                    'created_at' => $supplier['created_at'],
                    'updated_at' => $supplier['updated_at'],
                ]
            );
        }
    }
}
