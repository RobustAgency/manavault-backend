<?php

namespace Tests\Feature\Repositories;

use Mockery;
use Tests\TestCase;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\WithFaker;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\Ezcards\PlaceOrder as EzcardsPlaceOrder;
use App\Actions\Gift2Games\CreateOrder as Gift2GamesCreateOrder;

class PurchaseOrderRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function it_creates_purchase_order_for_ez_cards_supplier()
    {
        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'sku' => 'TEST-SKU-123',
            'purchase_price' => 10.00,
        ]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);
        $mockEzcardsAction->shouldReceive('execute')
            ->once()
            ->withArgs(function ($args) use ($product) {
                return $args['product_sku'] === $product->sku
                    && $args['quantity'] === 5
                    && isset($args['order_number']);
            })
            ->andReturn([
                'success' => true,
                'data' => ['transactionId' => 'TXN-12345'],
            ]);

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $purchaseOrder = $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 5,
        ]);

        // Assert
        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals($product->id, $purchaseOrder->product_id);
        $this->assertEquals($supplier->id, $purchaseOrder->supplier_id);
        $this->assertEquals(5, $purchaseOrder->quantity);
        $this->assertEquals(50.00, $purchaseOrder->total_price); // 10 * 5
        $this->assertEquals('TXN-12345', $purchaseOrder->transaction_id);
        $this->assertNotNull($purchaseOrder->order_number);
        $this->assertFalse((bool) $purchaseOrder->voucher_codes_processed);

        // Verify no vouchers created immediately for EZ Cards
        $this->assertEquals(0, $purchaseOrder->vouchers()->count());
    }

    public function it_creates_purchase_order_for_gift2games_supplier()
    {
        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'sku' => 'G2G-SKU-456',
            'purchase_price' => 20.00,
        ]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);
        $mockGift2GamesAction->shouldReceive('execute')
            ->once()
            ->withArgs(function ($args) use ($product) {
                return $args['product_sku'] === $product->sku
                    && $args['quantity'] === 10
                    && isset($args['order_number']);
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    'serialCode' => 'SERIAL-ABC-123',
                    'serialNumber' => 'SN-789456',
                ],
            ]);

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $purchaseOrder = $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 10,
        ]);

        // Assert
        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals($product->id, $purchaseOrder->product_id);
        $this->assertEquals($supplier->id, $purchaseOrder->supplier_id);
        $this->assertEquals(10, $purchaseOrder->quantity);
        $this->assertEquals(200.00, $purchaseOrder->total_price); // 20 * 10
        $this->assertNull($purchaseOrder->transaction_id); // Gift2Games doesn't use transaction_id

        // Verify voucher created immediately for Gift2Games
        $this->assertEquals(1, $purchaseOrder->vouchers()->count());

        $voucher = $purchaseOrder->vouchers()->first();
        $this->assertEquals('SERIAL-ABC-123', $voucher->code);
        $this->assertEquals('SN-789456', $voucher->serial_number);
        $this->assertEquals('COMPLETED', $voucher->status);
    }

    public function it_throws_exception_when_ez_cards_api_fails()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to place order with supplier ez_cards');

        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);
        $mockEzcardsAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 5,
        ]);
    }

    public function it_throws_exception_when_gift2games_api_fails()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to place order with supplier gift2games');

        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);
        $mockGift2GamesAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('API timeout'));

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 10,
        ]);
    }

    public function it_calculates_total_price_correctly()
    {
        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'purchase_price' => 15.50,
        ]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);
        $mockEzcardsAction->shouldReceive('execute')->andReturn([
            'success' => true,
            'data' => ['transactionId' => 'TXN-999'],
        ]);

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $purchaseOrder = $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 7,
        ]);

        // Assert
        $this->assertEquals(108.50, $purchaseOrder->total_price); // 15.50 * 7
    }

    public function it_generates_unique_order_number()
    {
        // Arrange
        $supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $mockEzcardsAction = Mockery::mock(EzcardsPlaceOrder::class);
        $mockEzcardsAction->shouldReceive('execute')->andReturn([
            'success' => true,
            'data' => ['transactionId' => 'TXN-001'],
        ]);

        $mockGift2GamesAction = Mockery::mock(Gift2GamesCreateOrder::class);

        $this->repository = new PurchaseOrderRepository(
            $mockEzcardsAction,
            $mockGift2GamesAction
        );

        // Act
        $order1 = $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 1,
        ]);

        $order2 = $this->repository->createPurchaseOrder([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 1,
        ]);

        // Assert
        $this->assertNotEquals($order1->order_number, $order2->order_number);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $order1->order_number);
    }

    public function it_can_get_filtered_purchase_orders()
    {
        PurchaseOrder::factory()->count(15)->create();

        $this->repository = app(PurchaseOrderRepository::class);
        $orders = $this->repository->getFilteredPurchaseOrders(['per_page' => 10]);

        $this->assertCount(10, $orders->items());
        $this->assertEquals(15, $orders->total());
    }

    public function it_can_filter_by_order_number()
    {
        $order = PurchaseOrder::factory()->create(['order_number' => 'ORDER-123-ABC']);
        PurchaseOrder::factory()->count(5)->create();

        $this->repository = app(PurchaseOrderRepository::class);
        $orders = $this->repository->getFilteredPurchaseOrders(['order_number' => '123']);

        $this->assertEquals(1, $orders->total());
        $this->assertEquals($order->id, $orders->first()->id);
    }

    public function it_can_filter_by_supplier_name()
    {
        $supplier = Supplier::factory()->create(['name' => 'Special Supplier']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        PurchaseOrder::factory()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
        ]);
        PurchaseOrder::factory()->count(3)->create();

        $this->repository = app(PurchaseOrderRepository::class);
        $orders = $this->repository->getFilteredPurchaseOrders(['supplier_name' => 'Special']);

        $this->assertEquals(1, $orders->total());
    }

    public function it_can_filter_by_product_name()
    {
        $product = Product::factory()->create(['name' => 'Unique Product']);
        PurchaseOrder::factory()->create(['product_id' => $product->id]);
        PurchaseOrder::factory()->count(4)->create();

        $this->repository = app(PurchaseOrderRepository::class);
        $orders = $this->repository->getFilteredPurchaseOrders(['product_name' => 'Unique']);

        $this->assertEquals(1, $orders->total());
    }

    public function it_loads_relationships_when_filtering()
    {
        PurchaseOrder::factory()->create();

        $this->repository = app(PurchaseOrderRepository::class);
        $orders = $this->repository->getFilteredPurchaseOrders([]);
        $firstOrder = $orders->items()[0];

        $this->assertTrue($firstOrder->relationLoaded('product'));
        $this->assertTrue($firstOrder->relationLoaded('supplier'));
        $this->assertNotNull($firstOrder->product);
        $this->assertNotNull($firstOrder->supplier);
    }
}
