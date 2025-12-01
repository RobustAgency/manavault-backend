<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalStockControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_get_paginated_digital_stocks(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(5)->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'brand',
                            'description',
                            'cost_price',
                            'quantity',
                            'supplier_id',
                            'supplier_name',
                            'supplier_type',
                            'metadata',
                            'source',
                            'last_synced_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital stocks retrieved successfully.',
            ]);
    }

    public function test_returns_correct_quantity_for_digital_products(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product->id,
            'quantity' => 25,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals(25, $data[0]['quantity']);
        $this->assertEquals($product->id, $data[0]['id']);
    }

    public function test_filters_external_suppliers_by_quantity(): void
    {
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);
        $productWithStock = DigitalProduct::factory()->create([
            'supplier_id' => $externalSupplier->id,
            'name' => 'Product With Stock',
        ]);
        $productWithoutStock = DigitalProduct::factory()->create([
            'supplier_id' => $externalSupplier->id,
            'name' => 'Product Without Stock',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $productWithStock->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals($productWithStock->id, $data[0]['id']);
        $this->assertEquals(10, $data[0]['quantity']);
    }

    public function test_shows_all_internal_supplier_products_regardless_of_quantity(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $product1 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $product2 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        // Only add stock to one product
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product1->id,
            'quantity' => 5,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(2, $data);
        $ids = array_map(fn ($item) => $item['id'], $data);
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
    }

    public function test_respects_per_page_parameter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(10)->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks?per_page=3');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(3, $data['per_page']);
        $this->assertEquals(10, $data['total']);
        $this->assertCount(3, $data['data']);
    }

    public function test_validates_per_page_parameter(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_validates_per_page_maximum(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_validates_per_page_minimum(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks?per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(401);
    }

    public function test_includes_supplier_information(): void
    {
        $supplier = Supplier::factory()->create([
            'type' => 'internal',
            'name' => 'Test Supplier Inc.',
        ]);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertEquals($supplier->id, $data['supplier_id']);
        $this->assertEquals('Test Supplier Inc.', $data['supplier_name']);
        $this->assertEquals('internal', $data['supplier_type']);
    }

    public function test_returns_zero_quantity_for_products_without_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertEquals(0, $data['quantity']);
    }

    public function test_aggregates_quantity_from_multiple_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder1->id,
            'digital_product_id' => $product->id,
            'quantity' => 10,
        ]);

        $purchaseOrder2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder2->id,
            'digital_product_id' => $product->id,
            'quantity' => 15,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks');

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertEquals(25, $data['quantity']);
    }

    // Tests for lowStockProducts endpoint

    public function test_admin_can_get_low_stock_products(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $lowStockProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $highStockProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $lowStockProduct->id,
            'quantity' => 3,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $highStockProduct->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'brand',
                            'description',
                            'cost_price',
                            'status',
                            'quantity',
                            'supplier_id',
                            'supplier_name',
                            'supplier_type',
                            'metadata',
                            'source',
                            'last_synced_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Low stock digital products retrieved successfully.',
            ]);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($lowStockProduct->id, $data[0]['id']);
        $this->assertEquals(3, $data[0]['quantity']);
    }

    public function test_low_stock_returns_only_products_below_threshold(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product3 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product4 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();

        // Quantity 0 (low stock)
        // product1 has no purchase order items

        // Quantity 4 (low stock)
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product2->id,
            'quantity' => 4,
        ]);

        // Quantity 5 (NOT low stock)
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product3->id,
            'quantity' => 5,
        ]);

        // Quantity 10 (NOT low stock)
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product4->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(2, $data);
        $ids = array_map(fn ($item) => $item['id'], $data);
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
        $this->assertNotContains($product3->id, $ids);
        $this->assertNotContains($product4->id, $ids);
    }

    public function test_low_stock_includes_internal_products_with_zero_quantity(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $productWithZeroStock = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals($productWithZeroStock->id, $data[0]['id']);
        $this->assertEquals(0, $data[0]['quantity']);
    }

    public function test_low_stock_excludes_external_products_with_zero_quantity(): void
    {
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);
        $productWithZeroStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $productWithLowStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $productWithLowStock->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals($productWithLowStock->id, $data[0]['id']);
        $ids = array_map(fn ($item) => $item['id'], $data);
        $this->assertNotContains($productWithZeroStock->id, $ids);
    }

    public function test_low_stock_respects_supplier_id_filter(): void
    {
        $supplier1 = Supplier::factory()->create(['type' => 'internal']);
        $supplier2 = Supplier::factory()->create(['type' => 'internal']);

        $product1 = DigitalProduct::factory()->create(['supplier_id' => $supplier1->id]);
        $product2 = DigitalProduct::factory()->create(['supplier_id' => $supplier2->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product1->id,
            'quantity' => 2,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product2->id,
            'quantity' => 3,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson("/api/admin/digital-stocks/low-stock?supplier_id={$supplier1->id}");

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals($product1->id, $data[0]['id']);
    }

    public function test_low_stock_respects_name_filter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Gift Card',
        ]);
        $nonMatchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Xbox Gift Card',
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?name=PlayStation');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals($matchingProduct->id, $data[0]['id']);
    }

    public function test_low_stock_respects_per_page_filter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(10)->create(['supplier_id' => $supplier->id]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?per_page=5');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(5, $data['per_page']);
        $this->assertEquals(10, $data['total']);
        $this->assertCount(5, $data['data']);
    }

    public function test_low_stock_validates_per_page_parameter(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_low_stock_validates_per_page_maximum(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_low_stock_validates_per_page_minimum(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_unauthenticated_user_cannot_access_low_stock(): void
    {
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(401);
    }

    public function test_low_stock_includes_supplier_information(): void
    {
        $supplier = Supplier::factory()->create([
            'type' => 'internal',
            'name' => 'Low Stock Supplier Inc.',
        ]);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200);
        $data = $response->json('data.data.0');

        $this->assertEquals($supplier->id, $data['supplier_id']);
        $this->assertEquals('Low Stock Supplier Inc.', $data['supplier_name']);
        $this->assertEquals('internal', $data['supplier_type']);
        $this->assertEquals(2, $data['quantity']);
    }

    public function test_low_stock_returns_empty_when_all_products_are_well_stocked(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product->id,
            'quantity' => 50,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertEmpty($data);
        $this->assertEquals(0, $response->json('data.total'));
    }

    public function test_low_stock_with_mixed_suppliers(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);

        $internalLow = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $internalHigh = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $externalLow = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $externalHigh = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $externalZero = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $internalLow->id,
            'quantity' => 1,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $internalHigh->id,
            'quantity' => 20,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalLow->id,
            'quantity' => 3,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalHigh->id,
            'quantity' => 15,
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/digital-stocks/low-stock?per_page=100');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(2, $data);
        $ids = array_map(fn ($item) => $item['id'], $data);
        $this->assertContains($internalLow->id, $ids);
        $this->assertContains($externalLow->id, $ids);
        $this->assertNotContains($internalHigh->id, $ids);
        $this->assertNotContains($externalHigh->id, $ids);
        $this->assertNotContains($externalZero->id, $ids);
    }
}
