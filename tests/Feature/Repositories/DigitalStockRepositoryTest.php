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

    // Tests for getLowDigitalStocks method

    public function test_low_stock_returns_only_products_with_quantity_less_than_five(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $lowStockProduct1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $lowStockProduct2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $highStockProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();

        // Low stock products (< 5)
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $lowStockProduct1->id,
            'quantity' => 2,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $lowStockProduct2->id,
            'quantity' => 4,
        ]);

        // High stock product (>= 5)
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $highStockProduct->id,
            'quantity' => 10,
        ]);

        $result = $this->repository->getLowDigitalStocks();

        $this->assertEquals(2, $result->total());
        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($lowStockProduct1->id, $ids);
        $this->assertContains($lowStockProduct2->id, $ids);
        $this->assertNotContains($highStockProduct->id, $ids);
    }

    public function test_low_stock_includes_internal_products_with_zero_quantity(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $productWithZeroStock = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $productWithLowStock = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $productWithLowStock->id,
            'quantity' => 3,
        ]);

        $result = $this->repository->getLowDigitalStocks();

        $this->assertEquals(2, $result->total());
        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($productWithZeroStock->id, $ids);
        $this->assertContains($productWithLowStock->id, $ids);

        // Verify zero stock product has 0 quantity
        $zeroStockResult = collect($items)->firstWhere('id', $productWithZeroStock->id);
        $this->assertEquals(0, $zeroStockResult->quantity);
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

        $result = $this->repository->getLowDigitalStocks();

        // Should only include external product with stock > 0
        $this->assertEquals(1, $result->total());
        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);
        $this->assertContains($productWithLowStock->id, $ids);
        $this->assertNotContains($productWithZeroStock->id, $ids);
    }

    public function test_low_stock_excludes_external_products_with_five_or_more_quantity(): void
    {
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);
        $lowStockProduct = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $exactlyFiveProduct = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $highStockProduct = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $lowStockProduct->id,
            'quantity' => 3,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $exactlyFiveProduct->id,
            'quantity' => 5,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $highStockProduct->id,
            'quantity' => 8,
        ]);

        $result = $this->repository->getLowDigitalStocks();

        // Only product with quantity < 5 should be included
        $this->assertEquals(1, $result->total());
        $items = $result->items();
        $this->assertEquals($lowStockProduct->id, $items[0]->id);
    }

    public function test_low_stock_with_mixed_suppliers(): void
    {
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);

        // Internal products with low stock
        $internalLow1 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $internalLow2 = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);
        $internalHigh = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        // External products
        $externalLow = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $externalHigh = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $externalZero = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $internalLow1->id,
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
            'quantity' => 4,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalHigh->id,
            'quantity' => 15,
        ]);

        $result = $this->repository->getLowDigitalStocks(['per_page' => 100]);

        // Should return: 2 internal low stock + 1 external low stock = 3 total
        $this->assertEquals(3, $result->total());
        $items = $result->items();
        $ids = array_map(fn ($item) => $item->id, $items);

        $this->assertContains($internalLow1->id, $ids);
        $this->assertContains($internalLow2->id, $ids); // zero stock internal
        $this->assertContains($externalLow->id, $ids);
        $this->assertNotContains($internalHigh->id, $ids);
        $this->assertNotContains($externalHigh->id, $ids);
        $this->assertNotContains($externalZero->id, $ids);
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

        $result = $this->repository->getLowDigitalStocks(['supplier_id' => $supplier1->id]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($product1->id, $result->items()[0]->id);
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

        $result = $this->repository->getLowDigitalStocks(['name' => 'PlayStation']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_respects_brand_filter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Sony',
        ]);
        $nonMatchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Microsoft',
        ]);

        $result = $this->repository->getLowDigitalStocks(['brand' => 'Sony']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_respects_per_page_filter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(10)->create(['supplier_id' => $supplier->id]);

        $result = $this->repository->getLowDigitalStocks(['per_page' => 5]);

        $this->assertEquals(10, $result->total());
        $this->assertEquals(5, $result->perPage());
        $this->assertCount(5, $result->items());
    }

    public function test_low_stock_includes_supplier_information(): void
    {
        $supplier = Supplier::factory()->create([
            'type' => 'internal',
            'name' => 'Low Stock Supplier',
        ]);
        $product = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->getLowDigitalStocks();

        $productResult = $result->items()[0];
        $this->assertEquals('Low Stock Supplier', $productResult->supplier_name);
        $this->assertEquals('internal', $productResult->supplier_type);
        $this->assertEquals(2, $productResult->quantity);
    }

    public function test_low_stock_results_are_ordered_by_id(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->count(5)->create(['supplier_id' => $supplier->id]);

        $result = $this->repository->getLowDigitalStocks(['per_page' => 100]);

        $ids = array_map(fn ($item) => $item->id, $result->items());
        $sortedIds = $ids;
        sort($sortedIds);

        $this->assertEquals($sortedIds, $ids);
    }

    public function test_low_stock_with_combined_filters_supplier_and_name(): void
    {
        $supplier1 = Supplier::factory()->create(['type' => 'internal']);
        $supplier2 = Supplier::factory()->create(['type' => 'internal']);

        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'PlayStation Gift Card',
        ]);

        $wrongSupplier = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'name' => 'PlayStation Store Card',
        ]);

        $wrongName = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'Xbox Gift Card',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $matchingProduct->id,
            'quantity' => 2,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $wrongSupplier->id,
            'quantity' => 3,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $wrongName->id,
            'quantity' => 1,
        ]);

        $result = $this->repository->getLowDigitalStocks([
            'supplier_id' => $supplier1->id,
            'name' => 'PlayStation',
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_with_combined_filters_supplier_and_brand(): void
    {
        $supplier1 = Supplier::factory()->create(['type' => 'internal']);
        $supplier2 = Supplier::factory()->create(['type' => 'internal']);

        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'brand' => 'Sony',
        ]);

        $wrongSupplier = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'brand' => 'Sony',
        ]);

        $wrongBrand = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'brand' => 'Microsoft',
        ]);

        $result = $this->repository->getLowDigitalStocks([
            'supplier_id' => $supplier1->id,
            'brand' => 'Sony',
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_with_combined_filters_name_and_brand(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);

        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Sony',
        ]);

        $wrongBrand = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Store Card',
            'brand' => 'Microsoft',
        ]);

        $wrongName = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Xbox Gift Card',
            'brand' => 'Sony',
        ]);

        $result = $this->repository->getLowDigitalStocks([
            'name' => 'PlayStation',
            'brand' => 'Sony',
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_with_all_filters_combined(): void
    {
        $supplier1 = Supplier::factory()->create(['type' => 'internal']);
        $supplier2 = Supplier::factory()->create(['type' => 'internal']);

        $matchingProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Sony',
        ]);

        $wrongSupplier = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Sony',
        ]);

        $wrongName = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'Xbox Gift Card',
            'brand' => 'Sony',
        ]);

        $wrongBrand = DigitalProduct::factory()->create([
            'supplier_id' => $supplier1->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Microsoft',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $matchingProduct->id,
            'quantity' => 3,
        ]);

        $result = $this->repository->getLowDigitalStocks([
            'supplier_id' => $supplier1->id,
            'name' => 'PlayStation',
            'brand' => 'Sony',
            'per_page' => 5,
        ]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals($matchingProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_name_filter_is_case_insensitive(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Gift Card',
        ]);

        $result = $this->repository->getLowDigitalStocks(['name' => 'playstation']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($product->id, $result->items()[0]->id);
    }

    public function test_low_stock_brand_filter_is_case_insensitive(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Sony',
        ]);

        $result = $this->repository->getLowDigitalStocks(['brand' => 'sony']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($product->id, $result->items()[0]->id);
    }

    public function test_low_stock_name_filter_with_partial_match(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation 5 Gift Card',
        ]);
        $product2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Store Card',
        ]);
        $product3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Xbox Gift Card',
        ]);

        $result = $this->repository->getLowDigitalStocks(['name' => 'Play', 'per_page' => 100]);

        $this->assertEquals(2, $result->total());
        $ids = array_map(fn ($item) => $item->id, $result->items());
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
        $this->assertNotContains($product3->id, $ids);
    }

    public function test_low_stock_brand_filter_with_partial_match(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Sony',
        ]);
        $product2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Sony Interactive',
        ]);
        $product3 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'brand' => 'Microsoft',
        ]);

        $result = $this->repository->getLowDigitalStocks(['brand' => 'Sony', 'per_page' => 100]);

        $this->assertEquals(2, $result->total());
        $ids = array_map(fn ($item) => $item->id, $result->items());
        $this->assertContains($product1->id, $ids);
        $this->assertContains($product2->id, $ids);
        $this->assertNotContains($product3->id, $ids);
    }

    public function test_low_stock_filters_return_empty_when_no_matches(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'PlayStation Gift Card',
            'brand' => 'Sony',
        ]);

        $result = $this->repository->getLowDigitalStocks(['name' => 'NonExistentProduct']);

        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }

    public function test_low_stock_with_external_supplier_filter(): void
    {
        $externalSupplier = Supplier::factory()->create(['type' => 'external']);
        $internalSupplier = Supplier::factory()->create(['type' => 'internal']);

        $externalProduct = DigitalProduct::factory()->create(['supplier_id' => $externalSupplier->id]);
        $internalProduct = DigitalProduct::factory()->create(['supplier_id' => $internalSupplier->id]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalProduct->id,
            'quantity' => 3,
        ]);

        $result = $this->repository->getLowDigitalStocks(['supplier_id' => $externalSupplier->id]);

        $this->assertEquals(1, $result->total());
        $this->assertEquals($externalProduct->id, $result->items()[0]->id);
    }

    public function test_low_stock_filters_work_with_multiple_suppliers(): void
    {
        $supplier1 = Supplier::factory()->create(['type' => 'internal', 'name' => 'Supplier A']);
        $supplier2 = Supplier::factory()->create(['type' => 'internal', 'name' => 'Supplier B']);
        $supplier3 = Supplier::factory()->create(['type' => 'external', 'name' => 'Supplier C']);

        DigitalProduct::factory()->count(2)->create([
            'supplier_id' => $supplier1->id,
            'brand' => 'Sony',
        ]);

        DigitalProduct::factory()->count(3)->create([
            'supplier_id' => $supplier2->id,
            'brand' => 'Sony',
        ]);

        $externalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier3->id,
            'brand' => 'Sony',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $externalProduct->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->getLowDigitalStocks(['brand' => 'Sony', 'per_page' => 100]);

        // 2 from supplier1 + 3 from supplier2 + 1 external with stock = 6 total
        $this->assertEquals(6, $result->total());
    }
}
