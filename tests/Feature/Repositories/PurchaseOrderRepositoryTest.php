<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
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

    public function test_get_paginated_purchase_orders(): void
    {
        PurchaseOrder::factory()->count(15)->create();

        $orders = $this->repository->getFilteredPurchaseOrders();

        $this->assertCount(10, $orders->items());
        $this->assertEquals(15, $orders->total());
    }

    public function test_get_paginated_purchase_orders_with_custom_per_page(): void
    {
        PurchaseOrder::factory()->count(25)->create();

        $orders = $this->repository->getFilteredPurchaseOrders(['per_page' => 5]);

        $this->assertCount(5, $orders->items());
        $this->assertEquals(25, $orders->total());
        $this->assertEquals(5, $orders->count());
    }
}
