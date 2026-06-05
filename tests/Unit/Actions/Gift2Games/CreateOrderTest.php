<?php

namespace Tests\Unit\Actions\Gift2Games;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Gift2Games\CreateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_successfully_creates_order(): void
    {
        $orderData = [
            'productId' => 12345,
            'referenceNumber' => 'PO-20251117-ABC123',
        ];

        $expectedResponse = [
            'status' => true,
            'data' => [
                'serialCode' => 'GIFT-CODE-123',
                'serialNumber' => 'SN-456',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $result = app(CreateOrder::class)->execute($orderData, 'gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertTrue($result['status']);
        $this->assertArrayHasKey('data', $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order');
        });
    }

    public function test_execute_throws_exception_when_order_creation_fails(): void
    {
        $orderData = [
            'productId' => 12345,
            'referenceNumber' => 'PO-20251117-ABC123',
        ];

        Http::fake([
            '*/create_order' => Http::response([
                'status' => false,
                'error' => ['message' => 'Invalid product ID'],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order creation failed: Invalid product ID');

        app(CreateOrder::class)->execute($orderData, 'gift2games');
    }

    public function test_execute_handles_api_error_response(): void
    {
        $orderData = [
            'productId' => 99999,
            'referenceNumber' => 'PO-20251117-XYZ789',
        ];

        Http::fake([
            '*/create_order' => Http::response([
                'status' => false,
                'error' => ['message' => 'Product not found'],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order creation failed: Product not found');

        app(CreateOrder::class)->execute($orderData, 'gift2games');
    }

    public function test_execute_with_complete_voucher_data(): void
    {
        $orderData = [
            'productId' => 54321,
            'referenceNumber' => 'PO-20251117-DEF456',
        ];

        $expectedResponse = [
            'status' => true,
            'data' => [
                'serialCode' => 'COMPLETE-CODE-789',
                'serialNumber' => 'SN-789',
                'pinCode' => 'PIN-1234',
                'expiryDate' => '2026-12-31',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $result = app(CreateOrder::class)->execute($orderData, 'gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('COMPLETE-CODE-789', $result['data']['serialCode']);
        $this->assertEquals('SN-789', $result['data']['serialNumber']);
        $this->assertEquals('PIN-1234', $result['data']['pinCode']);
    }

    public function test_execute_handles_network_timeout(): void
    {
        Http::fake([
            '*/create_order' => Http::response(null, 408),
        ]);

        $this->expectException(\Exception::class);

        app(CreateOrder::class)->execute(['productId' => 11111, 'referenceNumber' => 'PO-20251117-GHI789'], 'gift2games');
    }

    public function test_execute_handles_500_server_error(): void
    {
        Http::fake([
            '*/create_order' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->expectException(\Exception::class);

        app(CreateOrder::class)->execute(['productId' => 22222, 'referenceNumber' => 'PO-20251117-JKL012'], 'gift2games');
    }

    public function test_http_request_contains_correct_headers(): void
    {
        Http::fake([
            '*/create_order' => Http::response([
                'status' => true,
                'data' => ['serialCode' => 'TEST-CODE-001'],
            ], 200),
        ]);

        app(CreateOrder::class)->execute(['productId' => 33333, 'referenceNumber' => 'PO-20251117-MNO345'], 'gift2games');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type') &&
                   str_contains($request->url(), '/create_order');
        });
    }

    public function test_execute_creates_order_using_eur_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'serialCode' => 'EUR-CODE-001',
                'serialNumber' => 'SN-EUR-001',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $result = app(CreateOrder::class)->execute(
            ['productId' => 44444, 'referenceNumber' => 'PO-20251117-EUR001'],
            'gift-2-games-eur'
        );

        $this->assertEquals($expectedResponse, $result);
        $this->assertTrue($result['status']);
        $this->assertEquals('EUR-CODE-001', $result['data']['serialCode']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order'));
    }

    public function test_execute_creates_order_using_gbp_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'serialCode' => 'GBP-CODE-001',
                'serialNumber' => 'SN-GBP-001',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $result = app(CreateOrder::class)->execute(
            ['productId' => 55555, 'referenceNumber' => 'PO-20251117-GBP001'],
            'gift-2-games-gbp'
        );

        $this->assertEquals($expectedResponse, $result);
        $this->assertTrue($result['status']);
        $this->assertEquals('GBP-CODE-001', $result['data']['serialCode']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order'));
    }

    public function test_execute_throws_exception_for_unknown_supplier_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: gift-2-games-unknown');

        app(CreateOrder::class)->execute(
            ['productId' => 66666, 'referenceNumber' => 'PO-20251117-UNKNOWN'],
            'gift-2-games-unknown'
        );
    }
}
