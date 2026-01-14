<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\SaleOrder;
use App\Enums\SaleOrder\Status;
use App\Repositories\SaleOrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SaleOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(SaleOrderRepository::class);
    }

    public function test_create_sale_order(): void
    {
        $data = [
            'order_number' => 'SO-2026-000001',
            'source' => SaleOrder::MANASTORE,
            'currency' => 'usd',
            'total_price' => 100.00,
            'status' => Status::PENDING->value,
        ];

        $saleOrder = $this->repository->createSaleOrder($data);

        $this->assertInstanceOf(SaleOrder::class, $saleOrder);
        $this->assertEquals('SO-2026-000001', $saleOrder->order_number);
        $this->assertEquals(SaleOrder::MANASTORE, $saleOrder->source);
        $this->assertEquals(100.00, $saleOrder->total_price);
    }

    public function test_get_sale_order_by_id(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $result = $this->repository->getSaleOrderById($saleOrder->id);

        $this->assertInstanceOf(SaleOrder::class, $result);
        $this->assertEquals($saleOrder->id, $result->id);
    }

    public function test_get_sale_order_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sale order with ID 999 not found.');

        $this->repository->getSaleOrderById(999);
    }

    public function test_get_sale_order_by_order_number(): void
    {
        $saleOrder = SaleOrder::factory()->create(['order_number' => 'SO-2026-000001']);

        $result = $this->repository->getSaleOrderByOrderNumber('SO-2026-000001');

        $this->assertInstanceOf(SaleOrder::class, $result);
        $this->assertEquals('SO-2026-000001', $result->order_number);
    }

    public function test_get_sale_order_by_order_number_returns_null_when_not_found(): void
    {
        $result = $this->repository->getSaleOrderByOrderNumber('SO-2026-999999');

        $this->assertNull($result);
    }

    public function test_get_all_sale_orders(): void
    {
        SaleOrder::factory()->count(3)->create();

        $result = $this->repository->getAllSaleOrders();

        $this->assertCount(3, $result);
    }

    public function test_get_filtered_sale_orders(): void
    {
        SaleOrder::factory()->create(['status' => Status::PENDING->value]);
        SaleOrder::factory()->create(['status' => Status::COMPLETED->value]);

        $result = $this->repository->getFilteredSaleOrders(['status' => Status::PENDING->value]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(Status::PENDING->value, $result->items()[0]->status);
    }

    public function test_get_filtered_sale_orders_by_source(): void
    {
        SaleOrder::factory()->create(['source' => SaleOrder::MANASTORE]);
        SaleOrder::factory()->create(['source' => 'api']);

        $result = $this->repository->getFilteredSaleOrders(['source' => SaleOrder::MANASTORE]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(SaleOrder::MANASTORE, $result->items()[0]->source);
    }

    public function test_get_filtered_sale_orders_by_order_number(): void
    {
        SaleOrder::factory()->create(['order_number' => 'SO-2026-000001']);
        SaleOrder::factory()->create(['order_number' => 'SO-2026-000002']);

        $result = $this->repository->getFilteredSaleOrders(['order_number' => '000001']);

        $this->assertCount(1, $result->items());
        $this->assertEquals('SO-2026-000001', $result->items()[0]->order_number);
    }

    public function test_get_sale_orders_by_status(): void
    {
        SaleOrder::factory()->create(['status' => Status::PENDING->value]);
        SaleOrder::factory()->create(['status' => Status::PENDING->value]);
        SaleOrder::factory()->create(['status' => Status::COMPLETED->value]);

        $result = $this->repository->getSaleOrdersByStatus(Status::PENDING->value);

        $this->assertCount(2, $result);
        $result->each(fn ($order) => $this->assertEquals(Status::PENDING->value, $order->status));
    }

    public function test_update_sale_order(): void
    {
        $saleOrder = SaleOrder::factory()->create(['status' => Status::PENDING->value]);

        $updated = $this->repository->updateSaleOrder($saleOrder, [
            'status' => Status::COMPLETED->value,
            'total_price' => 250.00,
        ]);

        $this->assertEquals(Status::COMPLETED->value, $updated->status);
        $this->assertEquals(250.00, $updated->total_price);
    }

    public function test_delete_sale_order(): void
    {
        $saleOrder = SaleOrder::factory()->create();
        $id = $saleOrder->id;

        $result = $this->repository->deleteSaleOrder($id);

        $this->assertTrue($result);
        $this->assertNull(SaleOrder::find($id));
    }

    public function test_delete_sale_order_throws_exception_when_not_found(): void
    {
        $this->expectException(\Exception::class);

        $this->repository->deleteSaleOrder(999);
    }

    public function test_order_number_exists(): void
    {
        SaleOrder::factory()->create(['order_number' => 'SO-2026-000001']);

        $this->assertTrue($this->repository->orderNumberExists('SO-2026-000001'));
        $this->assertFalse($this->repository->orderNumberExists('SO-2026-999999'));
    }

    public function test_count_total_sale_orders(): void
    {
        SaleOrder::factory()->count(5)->create();

        $count = $this->repository->countTotalSaleOrders();

        $this->assertEquals(5, $count);
    }
}
