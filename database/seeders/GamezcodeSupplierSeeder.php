<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GamezcodeSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = [
            'name' => 'Gamezcode',
            'slug' => 'gamezcode',
            'type' => 'external',
            'contact_email' => null,
            'contact_phone' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $exists = DB::table('suppliers')
            ->where('slug', 'gamezcode')
            ->exists();

        if (! $exists) {
            DB::table('suppliers')->insert($supplier);
            $this->command->info('Gamezcode supplier created successfully.');
        } else {
            $this->command->info('Gamezcode supplier already exists.');
        }
    }
}
