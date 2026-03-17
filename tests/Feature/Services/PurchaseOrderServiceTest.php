<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Http;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchaseOrderService::class);
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

        $purchaseOrder = $this->service->createPurchaseOrder($data);

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

        $purchaseOrder = $this->service->createPurchaseOrder($data);

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

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('processing', $purchaseOrder->status);
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

        $purchaseOrder = $this->service->createPurchaseOrder($data);

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

    public function test_failed_external_supplier_does_not_affect_other_suppliers(): void
    {
        $externalSupplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $internalSupplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $externalProduct = DigitalProduct::factory()->create([
            'sku' => 'EXT-SKU-001',
            'cost_price' => 20.00,
        ]);

        $internalProduct = DigitalProduct::factory()->create([
            'sku' => 'INT-SKU-001',
            'cost_price' => 10.00,
        ]);

        // EzCards API fails
        Http::fake([
            '*/v2/orders' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $externalSupplier->id,
                    'digital_product_id' => $externalProduct->id,
                    'quantity' => 2,
                ],
                [
                    'supplier_id' => $internalSupplier->id,
                    'digital_product_id' => $internalProduct->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        // The overall PO is still created and returned
        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);

        // Overall PO status stays PROCESSING — one supplier failed, one is still processing
        $purchaseOrder->refresh();
        $this->assertEquals('processing', $purchaseOrder->status);

        // The failed external supplier is marked FAILED
        $this->assertDatabaseHas('purchase_order_suppliers', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $externalSupplier->id,
            'status' => 'failed',
        ]);

        // The internal supplier is still in PROCESSING (not failed)
        $this->assertDatabaseHas('purchase_order_suppliers', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $internalSupplier->id,
            'status' => 'processing',
        ]);

        // Internal supplier items ARE persisted
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $internalSupplier->id,
            'digital_product_id' => $internalProduct->id,
            'quantity' => 3,
        ]);

        // Failed external supplier items are NOT persisted (early return before item creation)
        $this->assertDatabaseMissing('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $externalSupplier->id,
        ]);

        // Total price reflects only the internal supplier's items
        $this->assertEquals(30.00, $purchaseOrder->total_price);
    }
}
