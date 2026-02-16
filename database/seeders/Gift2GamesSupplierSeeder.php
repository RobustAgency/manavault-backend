<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Gift2GamesSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'Gift2Games',
            'slug' => 'gift2games',
            'type' => 'external',
            'contact_email' => 'support@gift2games.com',
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Check if Gift2Games supplier already exists
        $exists = DB::table('suppliers')
            ->where('slug', 'gift2games')
            ->exists();

        if (! $exists) {
            DB::table('suppliers')->insert($supplier);
            $this->command->info('Gift2Games supplier created successfully.');
        } else {
            $this->command->info('Gift2Games supplier already exists.');
        }
    }
}
