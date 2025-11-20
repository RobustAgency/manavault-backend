<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Repositories\DigitalStockRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalStockRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DigitalStockRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DigitalStockRepository;
    }

    public function test_returns_all_internal_supplier_products_regardless_of_quantity(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $product1 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $product2 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        // Create purchase order for only one product
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product1->id,
            'quantity' => 10,
        ]);

        $result = $this->repository->getPaginatedDigitalStocks();

        $this->assertEquals(2, $result->total());
        $items = $result->items();
        $this->assertCount(2, $items);
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
    }

    public function test_returns_only_external_supplier_products_with_quantity_greater_than_zero(): void
    {
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);
        $productWithStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $productWithoutStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        // Create purchase order for only one product
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $productWithStock->id,
            'quantity' => 5,
        ]);

        $result = $this->repository->getPaginatedDigitalStocks();

        $this->assertEquals(1, $result->total());
        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($productWithStock->id, $ids);
        $this->assertNotContains($productWithoutStock->id, $ids);
    }

    public function test_correctly_calculates_total_quantity_from_multiple_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        // Create multiple purchase orders
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

        $result = $this->repository->getPaginatedDigitalStocks();

        $this->assertEquals(1, $result->total());
        $productResult = $result->items()[0];
        $this->assertEquals(25, $productResult->quantity);
    }

    public function test_shows_zero_quantity_for_products_without_purchase_orders(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        $result = $this->repository->getPaginatedDigitalStocks();

        $this->assertEquals(1, $result->total());
        $productResult = $result->items()[0];
        $this->assertEquals(0, $productResult->quantity);
    }

    public function test_includes_supplier_information_in_results(): void
    {
        $supplier = Supplier::factory()->create([
            'type' => 'internal',
            'name' => 'Test Supplier',
        ]);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $result = $this->repository->getPaginatedDigitalStocks();

        $productResult = $result->items()[0];
        $this->assertEquals('Test Supplier', $productResult->supplier_name);
        $this->assertEquals('internal', $productResult->supplier_type);
    }

    public function test_respects_per_page_filter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(10)->create(['supplier_id' => $supplier->id]);

        $result = $this->repository->getPaginatedDigitalStocks(['per_page' => 5]);

        $this->assertEquals(10, $result->total());
        $this->assertEquals(5, $result->perPage());
        $this->assertCount(5, $result->items());
    }

    public function test_mixed_suppliers_returns_correct_results(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);

        // Internal products (should all appear)
        $internalProduct1 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $internalProduct2 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        // External products
        $externalProductWithStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $externalProductWithoutStock = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        // Add stock to one external product
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalProductWithStock->id,
            'quantity' => 7,
        ]);

        $result = $this->repository->getPaginatedDigitalStocks(['per_page' => 100]);

        // Should return 2 internal + 1 external with stock = 3 total
        $this->assertEquals(3, $result->total());

        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($internalProduct1->id, $ids);
        $this->assertContains($internalProduct2->id, $ids);
        $this->assertContains($externalProductWithStock->id, $ids);
        $this->assertNotContains($externalProductWithoutStock->id, $ids);

        // Verify quantities
        $externalResult = collect($items)->firstWhere('id', $externalProductWithStock->id);
        $this->assertEquals(7, $externalResult->quantity);

        $internalResult = collect($items)->firstWhere('id', $internalProduct1->id);
        $this->assertEquals(0, $internalResult->quantity);
    }

    public function test_results_are_ordered_by_id(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(5)->create(['supplier_id' => $supplier->id]);

        $result = $this->repository->getPaginatedDigitalStocks(['per_page' => 100]);

        $ids = array_map(fn ($item) => $item->id, $result->items());
        $sortedIds = $ids;
        sort($sortedIds);

        $this->assertEquals($sortedIds, $ids);
    }
}
