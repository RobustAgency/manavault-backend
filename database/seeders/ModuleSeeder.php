<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['name' => 'User', 'slug' => 'user'],
            ['name' => 'Supplier', 'slug' => 'supplier'],
            ['name' => 'Supplier KPI', 'slug' => 'supplier_kpi'],
            ['name' => 'Product', 'slug' => 'product'],
            ['name' => 'Digital Product', 'slug' => 'digital_product'],
            ['name' => 'Purchase Order', 'slug' => 'purchase_order'],
            ['name' => 'Voucher', 'slug' => 'voucher'],
            ['name' => 'Voucher Audit Log', 'slug' => 'voucher_audit_log'],
            ['name' => 'Digital Stock', 'slug' => 'digital_stock'],
            ['name' => 'Brand', 'slug' => 'brand'],
            ['name' => 'Activity Log', 'slug' => 'activity_log'],
            ['name' => 'Price Rule', 'slug' => 'price_rule'],
            ['name' => 'Sale Order', 'slug' => 'sale_order'],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                ['name' => $module['name']]
            );
        }
    }
}
