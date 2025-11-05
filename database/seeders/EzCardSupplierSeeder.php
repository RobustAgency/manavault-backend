<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EzCardSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'EZ Cards',
            'slug' => 'ez_cards',
            'type' => 'external',
            'contact_email' => 'support@ezcards.com',
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Check if EZ Cards supplier already exists
        $exists = DB::table('suppliers')
            ->where('slug', 'ez_cards')
            ->exists();

        if (!$exists) {
            DB::table('suppliers')->insert($supplier);
            $this->command->info('EZ Cards supplier created successfully.');
        } else {
            $this->command->info('EZ Cards supplier already exists.');
        }
    }
}
