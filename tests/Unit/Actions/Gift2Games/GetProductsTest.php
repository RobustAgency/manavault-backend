<?php

namespace Tests\Unit\Actions\Gift2Games;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Gift2Games\GetProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_successfully_fetches_products(): void
    {
        // Arrange
        $expectedResponse = [
            'status' => true,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Amazon Gift Card $25',
                    'price' => 25.00,
                    'currency' => 'USD',
                ],
                [
                    'id' => 2,
                    'name' => 'iTunes Gift Card $50',
                    'price' => 50.00,
                    'currency' => 'USD',
                ],
            ],
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);

        // Verify the HTTP request was made
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/products') &&
                   $request->method() === 'GET';
        });
    }

    public function test_execute_returns_empty_array_when_no_products(): void
    {
        // Arrange
        $expectedResponse = [
            'status' => true,
            'data' => [],
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }

    public function test_execute_handles_api_error(): void
    {
        // Arrange
        Http::fake([
            '*/products' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Assert & Act
        $this->expectException(\Exception::class);
        $getProductsAction->execute();
    }

    public function test_execute_handles_unauthorized_error(): void
    {
        // Arrange
        Http::fake([
            '*/products' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Assert & Act
        $this->expectException(\Exception::class);
        $getProductsAction->execute();
    }

    public function test_execute_with_complete_product_details(): void
    {
        // Arrange
        $expectedResponse = [
            'status' => true,
            'data' => [
                [
                    'id' => 123,
                    'name' => 'PlayStation Store Gift Card $100',
                    'description' => 'Digital gift card for PlayStation Store',
                    'price' => 100.00,
                    'currency' => 'USD',
                    'category' => 'Gaming',
                    'sku' => 'PSN-100-USD',
                    'stock' => 50,
                    'active' => true,
                ],
            ],
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(123, $result['data'][0]['id']);
        $this->assertEquals('PlayStation Store Gift Card $100', $result['data'][0]['name']);
        $this->assertEquals(100.00, $result['data'][0]['price']);
        $this->assertEquals('PSN-100-USD', $result['data'][0]['sku']);
    }

    public function test_execute_handles_network_timeout(): void
    {
        // Arrange
        Http::fake([
            '*/products' => Http::response(null, 408), // Request Timeout
        ]);

        $getProductsAction = app(GetProducts::class);

        // Assert & Act
        $this->expectException(\Exception::class);
        $getProductsAction->execute();
    }

    public function test_execute_handles_large_product_list(): void
    {
        // Arrange
        $products = [];
        for ($i = 1; $i <= 100; $i++) {
            $products[] = [
                'id' => $i,
                'name' => "Product $i",
                'price' => $i * 10,
                'currency' => 'USD',
            ];
        }

        $expectedResponse = [
            'status' => true,
            'data' => $products,
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(100, $result['data']);
        $this->assertEquals(1, $result['data'][0]['id']);
        $this->assertEquals(100, $result['data'][99]['id']);
    }

    public function test_http_request_is_sent_with_correct_method(): void
    {
        // Arrange
        $expectedResponse = [
            'status' => true,
            'data' => [],
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        Http::assertSent(function ($request) {
            return $request->method() === 'GET' &&
                   str_contains($request->url(), '/products');
        });
    }

    public function test_execute_handles_malformed_response(): void
    {
        // Arrange
        Http::fake([
            '*/products' => Http::response('Invalid JSON response', 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Assert & Act
        $this->expectException(\TypeError::class);
        $getProductsAction->execute();
    }

    public function test_execute_with_pagination_metadata(): void
    {
        // Arrange
        $expectedResponse = [
            'status' => true,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Product 1',
                    'price' => 25.00,
                ],
            ],
            'meta' => [
                'current_page' => 1,
                'total_pages' => 5,
                'total_items' => 50,
                'per_page' => 10,
            ],
        ];

        Http::fake([
            '*/products' => Http::response($expectedResponse, 200),
        ]);

        $getProductsAction = app(GetProducts::class);

        // Act
        $result = $getProductsAction->execute();

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(1, $result['meta']['current_page']);
        $this->assertEquals(5, $result['meta']['total_pages']);
    }
}
