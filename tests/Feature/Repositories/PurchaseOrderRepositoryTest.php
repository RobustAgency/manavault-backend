<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\WithFaker;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseOrderRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PurchaseOrderRepository::class);
    }

    public function test_create_purchase_order(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => $this->faker->randomFloat(2, 10, 500),
            'quantity' => $this->faker->numberBetween(1, 100),
        ];

        $purchaseOrder = $this->repository->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertDatabaseHas('purchase_orders', [
            'product_id' => $data['product_id'],
            'supplier_id' => $data['supplier_id'],
            'purchase_price' => $data['purchase_price'],
            'quantity' => $data['quantity'],
        ]);
    }

    public function test_get_paginated_purchase_orders(): void
    {
        PurchaseOrder::factory()->count(15)->create();

        $orders = $this->repository->getPaginatedPurchaseOrders();

        $this->assertCount(10, $orders->items()); // Default per_page is 10
        $this->assertEquals(15, $orders->total());
    }

    public function test_get_paginated_purchase_orders_with_custom_per_page(): void
    {
        PurchaseOrder::factory()->count(25)->create();

        $orders = $this->repository->getPaginatedPurchaseOrders(['per_page' => 5]);

        $this->assertCount(5, $orders->items());
        $this->assertEquals(25, $orders->total());
        $this->assertEquals(5, $orders->count());
    }

    public function test_paginated_purchase_orders_loads_relationships(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $orders = $this->repository->getPaginatedPurchaseOrders();
        $firstOrder = $orders->items()[0];

        $this->assertTrue($firstOrder->relationLoaded('product'));
        $this->assertTrue($firstOrder->relationLoaded('supplier'));
        $this->assertNotNull($firstOrder->product);
        $this->assertNotNull($firstOrder->supplier);
    }
}
