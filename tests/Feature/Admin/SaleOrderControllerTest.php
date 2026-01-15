<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrder\Status;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_list_all_sale_orders(): void
    {
        SaleOrder::factory()->count(5)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'order_number',
                            'source',
                            'total_price',
                            'status',
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
                'message' => 'Sale orders retrieved successfully.',
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['total']);
    }

    public function test_admin_can_filter_sale_orders_by_order_number(): void
    {
        $orderNumber = 'SO-2026-000001';
        SaleOrder::factory()->create(['order_number' => $orderNumber]);
        SaleOrder::factory()->count(3)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?order_number=000001');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data['data']);
        $this->assertEquals($orderNumber, $data['data'][0]['order_number']);
    }

    public function test_admin_can_filter_sale_orders_by_status(): void
    {
        SaleOrder::factory()->create(['status' => Status::PENDING->value]);
        SaleOrder::factory()->create(['status' => Status::COMPLETED->value]);
        SaleOrder::factory()->count(2)->create(['status' => Status::PENDING->value]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?status='.Status::PENDING->value);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data['data']);
        $this->assertTrue(
            collect($data['data'])->every(fn ($order) => $order['status'] === Status::PENDING->value)
        );
    }

    public function test_admin_can_filter_sale_orders_by_source(): void
    {
        SaleOrder::factory()->create(['source' => 'manastore']);
        SaleOrder::factory()->create(['source' => 'external']);
        SaleOrder::factory()->count(2)->create(['source' => 'manastore']);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?source=manastore');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data['data']);
        $this->assertTrue(
            collect($data['data'])->every(fn ($order) => $order['source'] === 'manastore')
        );
    }

    public function test_admin_can_navigate_paginated_results(): void
    {
        SaleOrder::factory()->count(25)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?per_page=10&page=2');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(2, $data['current_page']);
        $this->assertCount(10, $data['data']);
    }

    public function test_sale_orders_are_sorted_by_created_at_descending(): void
    {
        $order1 = SaleOrder::factory()->create();
        sleep(1);
        $order2 = SaleOrder::factory()->create();
        sleep(1);
        $order3 = SaleOrder::factory()->create();

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?per_page=100');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        // Should be in reverse order of creation
        $this->assertEquals($order3->id, $data[0]['id']);
        $this->assertEquals($order2->id, $data[1]['id']);
        $this->assertEquals($order1->id, $data[2]['id']);
    }

    public function test_admin_can_view_single_sale_order(): void
    {
        $product = Product::factory()->create();
        $saleOrder = SaleOrder::factory()->create();
        SaleOrderItem::factory()->count(2)->forSaleOrder($saleOrder)->forProduct($product)->create();

        $this->actingAs($this->admin);
        $response = $this->getJson("/api/admin/sale-orders/{$saleOrder->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'source',
                    'total_price',
                    'status',
                    'items' => [
                        '*' => [
                            'id',
                            'quantity',
                            'unit_price',
                            'subtotal',
                            'digital_products',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Sale order retrieved successfully.',
                'data' => [
                    'id' => $saleOrder->id,
                    'order_number' => $saleOrder->order_number,
                    'source' => $saleOrder->source,
                    'status' => $saleOrder->status,
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_sale_order(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders/999');

        $response->assertStatus(404);
    }

    public function test_multiple_filters_can_be_applied_together(): void
    {
        SaleOrder::factory()->create([
            'order_number' => 'SO-2026-000001',
            'status' => Status::PENDING->value,
            'source' => 'manastore',
        ]);
        SaleOrder::factory()->create([
            'order_number' => 'SO-2026-000002',
            'status' => Status::COMPLETED->value,
            'source' => 'manastore',
        ]);
        SaleOrder::factory()->create([
            'order_number' => 'SO-2026-000003',
            'status' => Status::PENDING->value,
            'source' => 'manastore',
        ]);

        $this->actingAs($this->admin);
        $response = $this->getJson('/api/admin/sale-orders?order_number=000001&status='.Status::PENDING->value);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['total']);
        $this->assertStringContainsString('SO-2026-000001', $data['data'][0]['order_number']);
        $this->assertEquals(Status::PENDING->value, $data['data'][0]['status']);
    }
}
