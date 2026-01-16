<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissions = [
            // Users (view, create, edit, delete)
            'view_user',
            'create_user',
            'edit_user',
            'delete_user',

            // Suppliers (view, create, edit, delete)
            'view_supplier',
            'create_supplier',
            'edit_supplier',
            'delete_supplier',

            // Groups (view, create, edit, delete)
            'view_group',
            'create_group',
            'edit_group',
            'delete_group',

            // Roles (view, create, edit, delete)
            'view_role',
            'create_role',
            'edit_role',
            'delete_role',

            // Products (view, create, edit, delete)
            'view_product',
            'create_product',
            'edit_product',
            'delete_product',

            // Digital Products (view, create, edit, delete)
            'view_digital_product',
            'create_digital_product',
            'edit_digital_product',
            'delete_digital_product',

            // Purchase Orders (view, create only - no edit/delete)
            'view_purchase_order',
            'create_purchase_order',

            // Vouchers (view, create only - no edit/delete)
            'view_voucher',
            'create_voucher',

            // Digital Stocks (view only - no create/edit/delete)
            'view_digital_stock',

            // Brands (view, create, edit, delete)
            'view_brand',
            'create_brand',
            'edit_brand',
            'delete_brand',

            // Voucher Audit Logs (view only - no create/edit/delete)
            'view_voucher_audit_log',

            // Activity Logs (view only - no create/edit/delete)
            'view_activity_log',

            // Price Rules (view, create, edit, delete)
            'view_price_rule',
            'create_price_rule',
            'edit_price_rule',
            'delete_price_rule',

            // Sale Orders (view only - no create/edit/delete)
            'view_sale_order',
        ];

        // Create permissions with 'api' guard
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['name' => $permission, 'guard_name' => 'api']
            );
        }
    }
}
