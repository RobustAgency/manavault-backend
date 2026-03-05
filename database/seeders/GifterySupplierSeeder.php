<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class GifterySupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'Giftery',
            'slug' => 'giftery',
            'type' => 'external',
            'contact_email' => 'support@giftery.com',
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Supplier::firstOrCreate(
            ['slug' => 'giftery'],
            $supplier
        );
    }
}
