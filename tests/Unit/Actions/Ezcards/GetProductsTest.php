<?php

namespace Tests\Unit\Actions\Ezcards;

use Tests\TestCase;
use App\Actions\Ezcards\GetProducts;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_successfully_fetches_products(): void
    {
        $limit = 10;
        $page = 1;

        $expectedResponse = [
            'requestId' => 'abc123-def456',
            'data' => [
                [
                    'sku' => 'AAU-QB-Q1J',
                    'name' => 'Adidas Gift Card $25',
                    'price' => 22.88,
                    'currency' => 'USD',
                    'active' => true,
                ],
                [
                    'sku' => 'SMS-1B-I4A',
                    'name' => 'Steam Gift Card $50',
                    'price' => 45.50,
                    'currency' => 'USD',
                    'active' => true,
                ],
            ],
            'meta' => [
                'currentPage' => 1,
                'totalPages' => 5,
                'totalItems' => 50,
            ],
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);

        Http::assertSent(function ($request) use ($limit, $page) {
            return str_contains($request->url(), '/v2/products') &&
                   str_contains($request->url(), "limit=$limit") &&
                   str_contains($request->url(), "page=$page") &&
                   $request->method() === 'GET';
        });
    }

    public function test_execute_with_custom_limit_and_page(): void
    {
        $limit = 50;
        $page = 3;

        $expectedResponse = [
            'requestId' => 'xyz789-uvw012',
            'data' => array_fill(0, 50, [
                'sku' => 'TEST-SKU',
                'name' => 'Test Product',
                'price' => 10.00,
            ]),
            'meta' => [
                'currentPage' => 3,
                'totalPages' => 10,
            ],
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(50, $result['data']);
        $this->assertEquals(3, $result['meta']['currentPage']);

        Http::assertSent(function ($request) use ($limit, $page) {
            return str_contains($request->url(), "limit=$limit") &&
                   str_contains($request->url(), "page=$page");
        });
    }

    public function test_execute_with_max_limit(): void
    {
        $limit = 1000;
        $page = 1;

        $expectedResponse = [
            'requestId' => 'max-limit-test',
            'data' => array_fill(0, 1000, [
                'sku' => 'SKU-001',
                'name' => 'Product',
                'price' => 5.00,
            ]),
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(1000, $result['data']);
    }

    public function test_execute_returns_empty_array_when_no_products(): void
    {
        $limit = 10;
        $page = 100;

        $expectedResponse = [
            'requestId' => 'empty-response',
            'data' => [],
            'meta' => [
                'currentPage' => 100,
                'totalPages' => 10,
                'totalItems' => 100,
            ],
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEmpty($result['data']);
    }

    public function test_execute_with_complete_product_details(): void
    {
        $limit = 1;
        $page = 1;

        $expectedResponse = [
            'requestId' => 'complete-details-test',
            'data' => [
                [
                    'sku' => 'PSN-100-USD',
                    'name' => 'PlayStation Store Gift Card $100',
                    'description' => 'Digital gift card for PlayStation Store',
                    'price' => 95.00,
                    'currency' => 'USD',
                    'category' => 'Gaming',
                    'active' => true,
                    'stock' => 100,
                    'minQuantity' => 1,
                    'maxQuantity' => 10,
                ],
            ],
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('PSN-100-USD', $result['data'][0]['sku']);
        $this->assertEquals(95.00, $result['data'][0]['price']);
        $this->assertTrue($result['data'][0]['active']);
    }

    public function test_execute_handles_unauthorized_error(): void
    {
        $limit = 10;
        $page = 1;

        Http::fake([
            '*/v2/products*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $getProductsAction = app(GetProducts::class);

        $this->expectException(\Exception::class);
        $getProductsAction->execute($limit, $page);
    }

    public function test_execute_handles_network_timeout(): void
    {
        $limit = 10;
        $page = 1;

        Http::fake([
            '*/v2/products*' => Http::response(null, 408),
        ]);

        $getProductsAction = app(GetProducts::class);

        $this->expectException(\Exception::class);
        $getProductsAction->execute($limit, $page);
    }

    public function test_http_request_contains_correct_query_parameters(): void
    {
        $limit = 25;
        $page = 2;

        $expectedResponse = [
            'requestId' => 'query-params-test',
            'data' => [],
        ];

        Http::fake([
            '*/v2/products*' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        $result = $getProductsAction->execute($limit, $page);

        Http::assertSent(function ($request) use ($limit, $page) {
            $url = $request->url();

            return str_contains($url, '/v2/products') &&
                   str_contains($url, "limit=$limit") &&
                   str_contains($url, "page=$page") &&
                   $request->method() === 'GET';
        });
    }
}
