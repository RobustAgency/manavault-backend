<?php

namespace Tests\Unit\Actions\Ezcards;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Ezcards\GetVoucherCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetVoucherCodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_successfully_fetches_voucher_codes(): void
    {
        $transactionId = 1234;

        $expectedResponse = [
            'requestId' => '6f8028779c3fa879bd4a8f5176274793',
            'data' => [
                [
                    'sku' => 'AAU-QB-Q1J',
                    'quantity' => 2,
                    'codes' => [
                        [
                            'stockId' => '17675486',
                            'status' => 'COMPLETED',
                            'redeemCode' => '07241242-628b-4c4c-9180-7200a9726b1b',
                            'pinCode' => null,
                        ],
                        [
                            'stockId' => '17675487',
                            'status' => 'COMPLETED',
                            'redeemCode' => '1a45a5fb-fbf4-45a9-9a50-7f8f87a7c235',
                            'pinCode' => null,
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('requestId', $result);
        $this->assertCount(2, $result['data'][0]['codes']);

        Http::assertSent(function ($request) use ($transactionId) {
            return str_contains($request->url(), "/v2/orders/$transactionId/codes") &&
                   $request->method() === 'GET';
        });
    }

    public function test_execute_with_multiple_products(): void
    {
        $transactionId = 5678;

        $expectedResponse = [
            'requestId' => '7a9139880d4fb989ce5b9g6287385804',
            'data' => [
                [
                    'sku' => 'AAU-QB-Q1J',
                    'quantity' => 5,
                    'codes' => array_fill(0, 5, [
                        'stockId' => '17675486',
                        'status' => 'COMPLETED',
                        'redeemCode' => '07241242-628b-4c4c-9180-7200a9726b1b',
                        'pinCode' => null,
                    ]),
                ],
                [
                    'sku' => 'SMS-1B-I4A',
                    'quantity' => 3,
                    'codes' => array_fill(0, 3, [
                        'stockId' => '17675491',
                        'status' => 'COMPLETED',
                        'redeemCode' => '32dc69f6-eec8-4ff3-a260-bd386e868739',
                        'pinCode' => null,
                    ]),
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals('AAU-QB-Q1J', $result['data'][0]['sku']);
        $this->assertEquals('SMS-1B-I4A', $result['data'][1]['sku']);
    }

    public function test_execute_with_processing_status(): void
    {
        $transactionId = 9999;

        $expectedResponse = [
            'requestId' => '8b0240991e5gc090df6c0h7398496915',
            'data' => [
                [
                    'sku' => 'TEST-SKU-123',
                    'quantity' => 2,
                    'codes' => [
                        [
                            'stockId' => '17675500',
                            'status' => 'PROCESSING',
                            'redeemCode' => null,
                            'pinCode' => null,
                        ],
                        [
                            'stockId' => '17675501',
                            'status' => 'COMPLETED',
                            'redeemCode' => 'abc123-def456-ghi789',
                            'pinCode' => '1234',
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('PROCESSING', $result['data'][0]['codes'][0]['status']);
        $this->assertNull($result['data'][0]['codes'][0]['redeemCode']);
        $this->assertEquals('COMPLETED', $result['data'][0]['codes'][1]['status']);
        $this->assertNotNull($result['data'][0]['codes'][1]['redeemCode']);
    }

    public function test_execute_handles_not_found_error(): void
    {
        $transactionId = 0;

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response(['error' => 'Order not found'], 404),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $this->expectException(\Exception::class);
        $getVoucherCodesAction->execute($transactionId);
    }

    public function test_execute_handles_unauthorized_error(): void
    {
        $transactionId = 1111;

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $this->expectException(\Exception::class);
        $getVoucherCodesAction->execute($transactionId);
    }

    public function test_execute_with_empty_codes_array(): void
    {
        $transactionId = 3333;

        $expectedResponse = [
            'requestId' => '9c1351002f6hd101eg7d1i8409507026',
            'data' => [
                [
                    'sku' => 'EMPTY-SKU-456',
                    'quantity' => 0,
                    'codes' => [],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEmpty($result['data'][0]['codes']);
    }

    public function test_execute_with_pin_codes(): void
    {
        $transactionId = 4444;

        $expectedResponse = [
            'requestId' => '0d2462113g7ie212fh8e2j9510618137',
            'data' => [
                [
                    'sku' => 'PIN-PRODUCT-789',
                    'quantity' => 1,
                    'codes' => [
                        [
                            'stockId' => '17675600',
                            'status' => 'COMPLETED',
                            'redeemCode' => 'xyz789-uvw456-rst123',
                            'pinCode' => '9876',
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('9876', $result['data'][0]['codes'][0]['pinCode']);
        $this->assertNotNull($result['data'][0]['codes'][0]['redeemCode']);
    }

    public function test_http_request_uses_correct_endpoint(): void
    {
        $transactionId = 7890;

        $expectedResponse = [
            'requestId' => '1e3573224h8jf323gi9f3k0621729248',
            'data' => [],
        ];

        Http::fake([
            '*/v2/orders/'.$transactionId.'/codes' => Http::response($expectedResponse, 200),
        ]);

        $getVoucherCodesAction = app(GetVoucherCodes::class);

        $result = $getVoucherCodesAction->execute($transactionId);

        Http::assertSent(function ($request) use ($transactionId) {
            return $request->method() === 'GET' &&
                   str_contains($request->url(), "/v2/orders/$transactionId/codes");
        });
    }
}
