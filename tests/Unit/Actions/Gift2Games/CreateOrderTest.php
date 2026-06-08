<?php

namespace Tests\Unit\Actions\Gift2Games;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Gift2Games\CreateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private array $orderData = [
        'productId' => 12345,
        'referenceNumber' => 'order_item_id_1',
    ];

    public function test_execute_returns_indexed_response_array_for_single_order(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'orderId' => 'ORD-001',
                'serialCode' => 'GIFT-CODE-123',
                'serialNumber' => 'SN-456',
            ],
        ];

        Http::fake(['*/create_order' => Http::response($expectedResponse, 200)]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 1);

        $this->assertCount(1, $results);
        $this->assertEquals($expectedResponse, $results[0]);
        Http::assertSentCount(1);
    }

    public function test_execute_fires_correct_number_of_parallel_requests(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => true,
                'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-1', 'serialNumber' => 'SN-1'],
            ], 200),
        ]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 5);

        $this->assertCount(5, $results);
        Http::assertSentCount(5);
    }

    public function test_execute_sends_correct_order_data_in_request(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => true,
                'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-1', 'serialNumber' => 'SN-1'],
            ], 200),
        ]);

        app(CreateOrder::class)->execute(['productId' => 12345, 'referenceNumber' => 'order_item_id_1'], 'gift2games', 1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order')
                && $request['productId'] == 12345
                && $request['referenceNumber'] === 'order_item_id_1';
        });
    }

    public function test_execute_returns_null_when_api_status_is_false(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => false,
                'error' => ['message' => 'Invalid product ID'],
            ], 200),
        ]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 1);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function test_execute_returns_null_for_http_error_response(): void
    {
        Http::fake([
            '*/create_order' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 1);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function test_execute_returns_null_for_timeout_response(): void
    {
        Http::fake(['*/create_order' => Http::response(null, 408)]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 1);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]);
    }

    public function test_execute_handles_partial_failures_in_pool(): void
    {
        Http::fake([
            '*/create_order' => Http::sequence()
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-1', 'serialCode' => 'CODE-1', 'serialNumber' => 'SN-1']], 200)
                ->push(['status' => false, 'error' => ['message' => 'Product unavailable']], 200)
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-3', 'serialCode' => 'CODE-3', 'serialNumber' => 'SN-3']], 200),
        ]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 3);

        $this->assertCount(3, $results);
        $this->assertCount(2, array_filter($results, fn ($r) => $r !== null));
        $this->assertCount(1, array_filter($results, fn ($r) => $r === null));
        Http::assertSentCount(3);
    }

    public function test_execute_returns_all_null_when_all_requests_fail(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => false,
                'error' => ['message' => 'Service unavailable'],
            ], 200),
        ]);

        $results = app(CreateOrder::class)->execute($this->orderData, 'gift2games', 3);

        $this->assertCount(3, $results);
        $this->assertEquals(array_fill(0, 3, null), $results);
        Http::assertSentCount(3);
    }

    public function test_execute_preserves_full_voucher_data_in_response(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'orderId' => 'ORD-789',
                'serialCode' => 'COMPLETE-CODE-789',
                'serialNumber' => 'SN-789',
                'pinCode' => 'PIN-1234',
                'expiryDate' => '2026-12-31',
            ],
        ];

        Http::fake(['*/create_order' => Http::response($expectedResponse, 200)]);

        $results = app(CreateOrder::class)->execute(
            ['productId' => 54321, 'referenceNumber' => 'order_item_id_2'],
            'gift2games',
            1
        );

        $this->assertEquals($expectedResponse, $results[0]);
        $this->assertEquals('COMPLETE-CODE-789', $results[0]['data']['serialCode']);
        $this->assertEquals('SN-789', $results[0]['data']['serialNumber']);
        $this->assertEquals('PIN-1234', $results[0]['data']['pinCode']);
    }

    public function test_execute_creates_orders_using_eur_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => ['orderId' => 'ORD-EUR-001', 'serialCode' => 'EUR-CODE-001', 'serialNumber' => 'SN-EUR-001'],
        ];

        Http::fake(['*/create_order' => Http::response($expectedResponse, 200)]);

        $results = app(CreateOrder::class)->execute(
            ['productId' => 44444, 'referenceNumber' => 'order_item_id_3'],
            'gift-2-games-eur',
            1
        );

        $this->assertCount(1, $results);
        $this->assertNotNull($results[0]);
        $this->assertEquals('EUR-CODE-001', $results[0]['data']['serialCode']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order'));
    }

    public function test_execute_creates_orders_using_gbp_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => ['orderId' => 'ORD-GBP-001', 'serialCode' => 'GBP-CODE-001', 'serialNumber' => 'SN-GBP-001'],
        ];

        Http::fake(['*/create_order' => Http::response($expectedResponse, 200)]);

        $results = app(CreateOrder::class)->execute(
            ['productId' => 55555, 'referenceNumber' => 'order_item_id_4'],
            'gift-2-games-gbp',
            1
        );

        $this->assertCount(1, $results);
        $this->assertNotNull($results[0]);
        $this->assertEquals('GBP-CODE-001', $results[0]['data']['serialCode']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order'));
    }

    public function test_execute_throws_exception_for_unknown_supplier_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: gift-2-games-unknown');

        app(CreateOrder::class)->execute(
            ['productId' => 66666, 'referenceNumber' => 'order_item_id_5'],
            'gift-2-games-unknown',
            1
        );
    }
}
