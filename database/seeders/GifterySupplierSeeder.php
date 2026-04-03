<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GifterySupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'Giftery API',
            'slug' => 'giftery-api',
            'type' => 'external',
            'contact_email' => 'support@giftery.pro',
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Check if Giftery supplier already exists
        $exists = DB::table('suppliers')
            ->where('slug', 'giftery')
            ->exists();

        if (! $exists) {
            DB::table('suppliers')->insert($supplier);
            $this->command->info('Giftery supplier created successfully.');
        } else {
            $this->command->info('Giftery supplier already exists.');
        }
    }
}
