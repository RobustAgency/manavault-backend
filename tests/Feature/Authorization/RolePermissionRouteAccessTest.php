<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Module;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RolePermissionRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $adminWithPermissions;

    private User $adminWithoutPermissions;

    private Role $adminRole;

    private Module $productModule;

    private Module $supplierModule;

    private Module $brandModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create super admin
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        // Create modules
        $this->productModule = Module::factory()->create(['name' => 'Product', 'slug' => 'product']);
        $this->supplierModule = Module::factory()->create(['name' => 'Supplier', 'slug' => 'supplier']);
        $this->brandModule = Module::factory()->create(['name' => 'Brand', 'slug' => 'brand']);

        // Create admin role
        $this->adminRole = Role::create(['name' => 'admin_role', 'guard_name' => 'supabase']);

        // Create admin users
        $this->adminWithPermissions = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->adminWithoutPermissions = User::factory()->create(['role' => UserRole::USER->value]);

        // Assign role to admin with permissions
        $this->adminWithPermissions->assignRole($this->adminRole);
    }

    public function test_super_admin_can_access_get_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->superAdmin)->getJson('/api/products');

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_access_get_products(): void
    {
        $this->createAndAssignPermission('view_product', $this->productModule, $this->adminRole);
        Product::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithPermissions)->getJson('/api/products');

        $response->assertStatus(200);
    }

    public function test_admin_without_permission_cannot_access_get_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithoutPermissions)->getJson('/api/products');

        $response->assertStatus(403);
    }

    public function test_admin_with_permission_can_create_product(): void
    {
        $this->createAndAssignPermission('create_product', $this->productModule, $this->adminRole);

        $response = $this->actingAs($this->adminWithPermissions)->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-001',
            'brand_id' => Brand::factory()->create()->id,
            'description' => 'Test description',
            'face_value' => 100,
            'discounts' => 20,
            'currency' => 'usd',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_without_permission_cannot_create_product(): void
    {
        $response = $this->actingAs($this->adminWithoutPermissions)->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-001',
            'brand_id' => Brand::factory()->create()->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_with_permission_can_update_product(): void
    {
        $this->createAndAssignPermission('edit_product', $this->productModule, $this->adminRole);
        $product = Product::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->postJson("/api/products/{$product->id}", [
            'name' => 'Updated Product',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_delete_product(): void
    {
        $this->createAndAssignPermission('delete_product', $this->productModule, $this->adminRole);
        $product = Product::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_view_suppliers(): void
    {
        $this->createAndAssignPermission('view_supplier', $this->supplierModule, $this->adminRole);
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithPermissions)->getJson('/api/suppliers');

        $response->assertStatus(200);
    }

    public function test_admin_without_permission_cannot_view_suppliers(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithoutPermissions)->getJson('/api/suppliers');

        $response->assertStatus(403);
    }

    public function test_admin_with_permission_can_create_supplier(): void
    {
        $this->createAndAssignPermission('create_supplier', $this->supplierModule, $this->adminRole);

        $response = $this->actingAs($this->adminWithPermissions)->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'email' => 'supplier@test.com',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_with_permission_can_update_supplier(): void
    {
        $this->createAndAssignPermission('edit_supplier', $this->supplierModule, $this->adminRole);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->postJson("/api/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_delete_supplier(): void
    {
        $this->createAndAssignPermission('delete_supplier', $this->supplierModule, $this->adminRole);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_view_brands(): void
    {
        $this->createAndAssignPermission('view_brand', $this->brandModule, $this->adminRole);
        Brand::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithPermissions)->getJson('/api/brands');

        $response->assertStatus(200);
    }

    public function test_admin_without_permission_cannot_view_brands(): void
    {
        Brand::factory()->count(3)->create();

        $response = $this->actingAs($this->adminWithoutPermissions)->getJson('/api/brands');

        $response->assertStatus(403);
    }

    public function test_admin_with_permission_can_create_brand(): void
    {
        $this->createAndAssignPermission('create_brand', $this->brandModule, $this->adminRole);

        $response = $this->actingAs($this->adminWithPermissions)->postJson('/api/brands', [
            'name' => 'Test Brand',
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_with_permission_can_update_brand(): void
    {
        $this->createAndAssignPermission('edit_brand', $this->brandModule, $this->adminRole);
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->putJson("/api/brands/{$brand->id}", [
            'name' => 'Updated Brand',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_delete_brand(): void
    {
        $this->createAndAssignPermission('delete_brand', $this->brandModule, $this->adminRole);
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->adminWithPermissions)->deleteJson("/api/brands/{$brand->id}");

        $response->assertStatus(200);
    }

    public function test_admin_with_permission_can_view_roles(): void
    {
        $roleModule = Module::factory()->create(['name' => 'Role', 'slug' => 'role']);
        $this->createAndAssignPermission('view_role', $roleModule, $this->adminRole);

        $response = $this->actingAs($this->adminWithPermissions)->getJson('/api/roles');

        $response->assertStatus(200);
    }

    public function test_admin_without_permission_cannot_view_roles(): void
    {
        $response = $this->actingAs($this->adminWithoutPermissions)->getJson('/api/roles');

        $response->assertStatus(403);
    }

    // ==================== MULTIPLE PERMISSIONS TEST ====================

    public function test_admin_with_multiple_permissions_can_access_all_routes(): void
    {
        // Assign multiple permissions
        $this->createAndAssignPermission('view_product', $this->productModule, $this->adminRole);
        $this->createAndAssignPermission('view_supplier', $this->supplierModule, $this->adminRole);
        $this->createAndAssignPermission('view_brand', $this->brandModule, $this->adminRole);

        Product::factory()->count(2)->create();
        Supplier::factory()->count(2)->create();
        Brand::factory()->count(2)->create();

        // Test all routes are accessible
        $this->actingAs($this->adminWithPermissions)
            ->getJson('/api/products')
            ->assertStatus(200);

        $this->actingAs($this->adminWithPermissions)
            ->getJson('/api/suppliers')
            ->assertStatus(200);

        $this->actingAs($this->adminWithPermissions)
            ->getJson('/api/brands')
            ->assertStatus(200);
    }

    public function test_admin_loses_access_when_permission_is_revoked(): void
    {
        $permission = $this->createAndAssignPermission('view_product', $this->productModule, $this->adminRole);
        Product::factory()->create();

        // Can access initially
        $this->actingAs($this->adminWithPermissions)
            ->getJson('/api/products')
            ->assertStatus(200);

        // Revoke permission
        $this->adminRole->revokePermissionTo($permission);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Cannot access after revoke
        $this->actingAs($this->adminWithPermissions)
            ->getJson('/api/products')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    private function createAndAssignPermission(string $permissionName, Module $module, Role $role): Permission
    {
        $permission = Permission::factory()->create([
            'name' => $permissionName,
            'guard_name' => 'supabase',
            'module_id' => $module->id,
            'action' => $permissionName,
        ]);

        $role->givePermissionTo($permission);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
