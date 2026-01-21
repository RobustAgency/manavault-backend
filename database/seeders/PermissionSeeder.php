<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $modules = [
            ['name' => 'User', 'slug' => 'user',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Supplier', 'slug' => 'supplier',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Supplier KPI', 'slug' => 'supplier_kpi',
                'permissions' => [
                    'view',
                ]],
            ['name' => 'Product', 'slug' => 'product',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Digital Product', 'slug' => 'digital_product',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Purchase Order', 'slug' => 'purchase_order',
                'permissions' => [
                    'view',
                    'create',
                ]],
            ['name' => 'Voucher', 'slug' => 'voucher',
                'permissions' => [
                    'view',
                    'create',
                ]],
            ['name' => 'Voucher Audit Log', 'slug' => 'voucher_audit_log',
                'permissions' => [
                    'view',
                ]],
            ['name' => 'Digital Stock', 'slug' => 'digital_stock',
                'permissions' => [
                    'view',
                ]],
            ['name' => 'Brand', 'slug' => 'brand',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Activity Log', 'slug' => 'activity_log',
                'permissions' => [
                    'view',
                ]],
            ['name' => 'Price Rule', 'slug' => 'price_rule',
                'permissions' => [
                    'view',
                    'create',
                    'edit',
                    'delete',
                ]],
            ['name' => 'Sale Order', 'slug' => 'sale_order',
                'permissions' => [
                    'view',
                ]],
        ];

        foreach ($modules as $module) {
            $dbModule = Module::firstOrCreate(
                ['slug' => $module['slug']],
                ['name' => $module['name']]
            );

            foreach ($module['permissions'] as $permissionAction) {
                $permissionName = $permissionAction.'_'.$module['slug'];

                Permission::firstOrCreate(
                    ['name' => $permissionName],
                    [
                        'module_id' => $dbModule->id,
                        'action' => $permissionAction,
                        'guard_name' => 'supabase',
                    ]
                );
            }
        }
    }
}
