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

        $createOrderAction = app(CreateOrder::class);

        $result = $createOrderAction->execute($orderData);

        $this->assertEquals($expectedResponse, $result);
        $this->assertTrue($result['status']);
        $this->assertArrayHasKey('data', $result);

        // Verify the HTTP request was made
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order');
        });
    }

    public function test_execute_throws_exception_when_order_creation_fails(): void
    {
        // Arrange
        $orderData = [
            'productId' => 12345,
            'referenceNumber' => 'PO-20251117-ABC123',
        ];

        $errorResponse = [
            'status' => false,
            'error' => [
                'message' => 'Invalid product ID',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($errorResponse, 200),
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order creation failed: Invalid product ID');

        // Act
        $createOrderAction->execute($orderData);
    }

    public function test_execute_handles_api_error_response(): void
    {
        // Arrange
        $orderData = [
            'productId' => 99999,
            'referenceNumber' => 'PO-20251117-XYZ789',
        ];

        $errorResponse = [
            'status' => false,
            'error' => [
                'message' => 'Product not found',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($errorResponse, 200),
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order creation failed: Product not found');

        // Act
        $createOrderAction->execute($orderData);
    }

    public function test_execute_with_empty_order_data(): void
    {
        // Arrange
        $orderData = [];

        $expectedResponse = [
            'status' => true,
            'data' => [],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Act
        $result = $createOrderAction->execute($orderData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order');
        });
    }

    public function test_execute_with_complete_voucher_data(): void
    {
        // Arrange
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

        $createOrderAction = app(CreateOrder::class);

        // Act
        $result = $createOrderAction->execute($orderData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('COMPLETE-CODE-789', $result['data']['serialCode']);
        $this->assertEquals('SN-789', $result['data']['serialNumber']);
        $this->assertEquals('PIN-1234', $result['data']['pinCode']);
    }

    public function test_execute_handles_network_timeout(): void
    {
        // Arrange
        $orderData = [
            'productId' => 11111,
            'referenceNumber' => 'PO-20251117-GHI789',
        ];

        Http::fake([
            '*/create_order' => Http::response(null, 408), // Request Timeout
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Assert & Act
        $this->expectException(\Exception::class);
        $createOrderAction->execute($orderData);
    }

    public function test_execute_handles_500_server_error(): void
    {
        // Arrange
        $orderData = [
            'productId' => 22222,
            'referenceNumber' => 'PO-20251117-JKL012',
        ];

        Http::fake([
            '*/create_order' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Assert & Act
        $this->expectException(\Exception::class);
        $createOrderAction->execute($orderData);
    }

    public function test_http_request_contains_correct_headers(): void
    {
        // Arrange
        $orderData = [
            'productId' => 33333,
            'referenceNumber' => 'PO-20251117-MNO345',
        ];

        $expectedResponse = [
            'status' => true,
            'data' => [
                'serialCode' => 'TEST-CODE-001',
            ],
        ];

        Http::fake([
            '*/create_order' => Http::response($expectedResponse, 200),
        ]);

        $createOrderAction = app(CreateOrder::class);

        // Act
        $result = $createOrderAction->execute($orderData);

        // Assert
        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type') &&
                   str_contains($request->url(), '/create_order');
        });
    }
}
