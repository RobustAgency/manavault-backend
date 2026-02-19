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
        // Step 1: Create all permissions first
        $this->createPermissions();

        // Step 2: Clean up old Digital Product module (if it exists)
        $this->cleanupOldDigitalProductModule();
    }

    /**
     * Create all module permissions.
     */
    private function createPermissions(): void
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
                    'create',
                    'edit',
                    'delete',
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

    /**
     * Clean up the old Digital Product module and its permissions.
     * Removes the duplicate digital_product slug used by Digital Stock.
     */
    private function cleanupOldDigitalProductModule(): void
    {
        $oldModule = Module::where('slug', 'digital_product')->first();

        if ($oldModule) {
            Permission::where('module_id', $oldModule->id)->delete();
            $oldModule->delete();
        }
    }
}
