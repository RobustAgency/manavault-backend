<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Http;
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

    public function test_create_purchase_order_with_gift2games_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Gift2Games',
            'slug' => 'gift2games',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => '12345',
            'cost_price' => 10.00,
        ]);

        Http::fake([
            '*/create_order' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => [
                        'referenceNumber' => 'PO-20251117-TEST',
                        'productId' => 12345,
                        'code' => 'VOUCHER-CODE-001',
                        'pin' => '1234',
                        'serial' => 'SERIAL-001',
                        'expiryDate' => '2025-12-31',
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => [
                        'referenceNumber' => 'PO-20251117-TEST',
                        'productId' => 12345,
                        'code' => 'VOUCHER-CODE-002',
                        'pin' => '5678',
                        'serial' => 'SERIAL-002',
                        'expiryDate' => '2025-12-31',
                    ],
                ], 200),
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $purchaseOrder = $this->repository->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('completed', $purchaseOrder->status);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertEquals(2, $purchaseOrder->vouchers()->count());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order') &&
                   $request->method() === 'POST';
        });
    }

    public function test_create_purchase_order_with_ezcards_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'AAU-QB-Q1J',
            'cost_price' => 22.88,
        ]);

        Http::fake([
            '*/v2/orders' => Http::response([
                'requestId' => 'test-request-id',
                'data' => [
                    'transactionId' => '1234',
                    'clientOrderNumber' => 'PO-20251117-TEST',
                    'status' => 'PROCESSING',
                    'grandTotal' => [
                        'amount' => '68.64',
                        'currency' => 'USD',
                    ],
                    'products' => [
                        [
                            'sku' => 'AAU-QB-Q1J',
                            'quantity' => 3,
                            'unitPrice' => ['amount' => '22.88', 'currency' => 'USD'],
                            'totalPrice' => ['amount' => '68.64', 'currency' => 'USD'],
                            'status' => 'PROCESSING',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->repository->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('processing', $purchaseOrder->status);
        $this->assertCount(1, $purchaseOrder->items);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/orders') &&
                   $request->method() === 'POST';
        });
    }

    public function test_create_purchase_order_with_internal_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'INTERNAL-SKU',
            'cost_price' => 15.00,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $purchaseOrder = $this->repository->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('completed', $purchaseOrder->status);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertEquals(75.00, $purchaseOrder->total_price);
    }

    public function test_create_purchase_order_with_multiple_items(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct1 = DigitalProduct::factory()->create([
            'sku' => 'AAU-QB-Q1J',
            'cost_price' => 22.88,
        ]);

        $digitalProduct2 = DigitalProduct::factory()->create([
            'sku' => 'SMS-1B-I4A',
            'cost_price' => 45.50,
        ]);

        Http::fake([
            '*/v2/orders' => Http::response([
                'requestId' => 'test-request-id',
                'data' => [
                    'transactionId' => '5678',
                    'clientOrderNumber' => 'PO-20251117-MULTI',
                    'status' => 'PROCESSING',
                    'grandTotal' => [
                        'amount' => '182.26',
                        'currency' => 'USD',
                    ],
                    'products' => [
                        [
                            'sku' => 'AAU-QB-Q1J',
                            'quantity' => 2,
                            'status' => 'PROCESSING',
                        ],
                        [
                            'sku' => 'SMS-1B-I4A',
                            'quantity' => 3,
                            'status' => 'PROCESSING',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct1->id,
                    'quantity' => 2,
                ],
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct2->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->repository->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('processing', $purchaseOrder->status);
        $this->assertCount(2, $purchaseOrder->items);
        $this->assertEquals(182.26, $purchaseOrder->total_price);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/orders') &&
                   $request->method() === 'POST' &&
                   count($request['products']) === 2;
        });
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
