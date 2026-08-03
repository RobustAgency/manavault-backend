<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class GamezcodeSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Gamezcode',
                'slug' => 'gamezcode',
                'type' => 'external',
                'contact_email' => 'support@kalixo.io',
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

            $this->command->info("Supplier [{$supplier['name']}] seeded successfully.");
        }
    }
}
