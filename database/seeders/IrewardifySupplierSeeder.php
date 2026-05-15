<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IrewardifySupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'Irewardify',
            'slug' => 'irewardify',
            'type' => 'external',
            'contact_email' => 'info@irewardify.com',
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Check if Irewardify supplier already exists
        $exists = DB::table('suppliers')
            ->where('slug', 'irewardify')
            ->exists();

        if (! $exists) {
            DB::table('suppliers')->insert($supplier);
            $this->command->info('Irewardify supplier created successfully.');
        } else {
            $this->command->info('Irewardify supplier already exists.');
        }
    }
}
