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
}
