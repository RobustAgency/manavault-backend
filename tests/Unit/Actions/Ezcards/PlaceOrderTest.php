<?php

namespace Tests\Unit\Actions\Ezcards;

use Tests\TestCase;
use App\Actions\Ezcards\PlaceOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_successfully_places_order(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-ABC123',
            'products' => [
                [
                    'sku' => 'AAU-QB-Q1J',
                    'quantity' => 2,
                ],
            ],
        ];

        $expectedResponse = [
            'requestId' => 'c4c7b997-79a5-4bde-9f17-47ad7eac9ed4',
            'data' => [
                'transactionId' => '1234',
                'clientOrderNumber' => 'PO-20251117-ABC123',
                'grandTotal' => [
                    'amount' => '45.76',
                    'currency' => 'USD',
                ],
                'createdAt' => '2025-11-17T09:00:00Z',
                'status' => 'PROCESSING',
                'products' => [
                    [
                        'sku' => 'AAU-QB-Q1J',
                        'quantity' => 2,
                        'unitPrice' => ['amount' => '22.88', 'currency' => 'USD'],
                        'totalPrice' => ['amount' => '45.76', 'currency' => 'USD'],
                        'status' => 'PROCESSING',
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response($expectedResponse, 200),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $result = $placeOrderAction->execute($orderData);

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('1234', $result['data']['transactionId']);
        $this->assertEquals('PROCESSING', $result['data']['status']);

        Http::assertSent(function ($request) use ($orderData) {
            return str_contains($request->url(), '/v2/orders') &&
                   $request->method() === 'POST' &&
                   $request['clientOrderNumber'] === $orderData['clientOrderNumber'];
        });
    }

    public function test_execute_with_multiple_products(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-XYZ789',
            'products' => [
                [
                    'sku' => 'AAU-QB-Q1J',
                    'quantity' => 5,
                ],
                [
                    'sku' => 'SMS-1B-I4A',
                    'quantity' => 3,
                ],
            ],
        ];

        $expectedResponse = [
            'requestId' => 'd5d8c008-80b6-5cef-0g28-58be8fbd0fe5',
            'data' => [
                'transactionId' => '5678',
                'clientOrderNumber' => 'PO-20251117-XYZ789',
                'grandTotal' => [
                    'amount' => '250.90',
                    'currency' => 'USD',
                ],
                'status' => 'PROCESSING',
                'products' => [
                    [
                        'sku' => 'AAU-QB-Q1J',
                        'quantity' => 5,
                        'status' => 'PROCESSING',
                    ],
                    [
                        'sku' => 'SMS-1B-I4A',
                        'quantity' => 3,
                        'status' => 'PROCESSING',
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response($expectedResponse, 200),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $result = $placeOrderAction->execute($orderData);

        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $result['data']['products']);
        $this->assertEquals('5678', $result['data']['transactionId']);
    }

    public function test_execute_with_completed_status(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-COMPLETED',
            'products' => [
                [
                    'sku' => 'ELX-BF-85S',
                    'quantity' => 1,
                ],
            ],
        ];

        $expectedResponse = [
            'requestId' => 'e6e9d119-91c7-6dfg-1h39-69cf9gce1gf6',
            'data' => [
                'transactionId' => '9999',
                'clientOrderNumber' => 'PO-20251117-COMPLETED',
                'status' => 'COMPLETED',
                'products' => [
                    [
                        'sku' => 'ELX-BF-85S',
                        'quantity' => 1,
                        'unitPrice' => ['amount' => '5.39', 'currency' => 'USD'],
                        'status' => 'COMPLETED',
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response($expectedResponse, 200),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $result = $placeOrderAction->execute($orderData);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('COMPLETED', $result['data']['status']);
        $this->assertEquals('COMPLETED', $result['data']['products'][0]['status']);
    }

    public function test_execute_throws_exception_on_validation_error(): void
    {
        $orderData = [
            'clientOrderNumber' => 123,
            'products' => [
                [
                    'sku' => 'TEST-SKU',
                    'quantity' => 1,
                ],
            ],
        ];

        $placeOrderAction = app(PlaceOrder::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "clientOrderNumber" must be a string');
        $placeOrderAction->execute($orderData);
    }

    public function test_execute_throws_exception_on_api_error(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-ERROR',
            'products' => [
                [
                    'sku' => 'INVALID-SKU',
                    'quantity' => 1,
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response(['error' => 'Invalid SKU'], 400),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $placeOrderAction->execute($orderData);
    }

    public function test_execute_handles_unauthorized_error(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-UNAUTH',
            'products' => [
                [
                    'sku' => 'TEST-SKU',
                    'quantity' => 1,
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $placeOrderAction->execute($orderData);
    }

    public function test_execute_with_currency_conversion(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-CURRENCY',
            'products' => [
                [
                    'sku' => 'TTR-WI-YWN',
                    'quantity' => 1,
                ],
            ],
        ];

        $expectedResponse = [
            'requestId' => 'f7f0e220-02d8-7egh-2i40-70dg0hdf2hg7',
            'data' => [
                'transactionId' => '8888',
                'clientOrderNumber' => 'PO-20251117-CURRENCY',
                'grandTotal' => [
                    'amount' => '11.48',
                    'currency' => 'USD',
                ],
                'products' => [
                    [
                        'sku' => 'TTR-WI-YWN',
                        'quantity' => 1,
                        'unitPriceOriginal' => ['amount' => '9.70', 'currency' => 'EUR'],
                        'unitPrice' => ['amount' => '11.48', 'currency' => 'USD'],
                        'status' => 'PROCESSING',
                    ],
                ],
                'fxSummary' => [
                    ['pair' => 'EUR/USD', 'rate' => 1.1829, 'asOfDate' => '2025-11-17'],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response($expectedResponse, 200),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $result = $placeOrderAction->execute($orderData);

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('fxSummary', $result['data']);
        $this->assertEquals('EUR', $result['data']['products'][0]['unitPriceOriginal']['currency']);
        $this->assertEquals('USD', $result['data']['products'][0]['unitPrice']['currency']);
    }

    public function test_http_request_sends_correct_payload(): void
    {
        $orderData = [
            'clientOrderNumber' => 'PO-20251117-PAYLOAD',
            'products' => [
                [
                    'sku' => 'TEST-SKU-001',
                    'quantity' => 10,
                ],
            ],
        ];

        $expectedResponse = [
            'requestId' => 'test-request-id',
            'data' => [
                'transactionId' => '1111',
                'status' => 'PROCESSING',
            ],
        ];

        Http::fake([
            '*/v2/orders' => Http::response($expectedResponse, 200),
        ]);

        $placeOrderAction = app(PlaceOrder::class);

        $result = $placeOrderAction->execute($orderData);

        Http::assertSent(function ($request) use ($orderData) {
            return $request->method() === 'POST' &&
                   str_contains($request->url(), '/v2/orders') &&
                   $request['clientOrderNumber'] === $orderData['clientOrderNumber'] &&
                   $request['products'][0]['sku'] === $orderData['products'][0]['sku'] &&
                   $request['products'][0]['quantity'] === $orderData['products'][0]['quantity'];
        });
    }
}
