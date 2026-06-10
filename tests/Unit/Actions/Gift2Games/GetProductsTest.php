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
        $expectedResponse = [
            'status' => true,
            'data' => [
                ['id' => 1, 'name' => 'Amazon Gift Card $25', 'price' => 25.00, 'currency' => 'USD'],
                ['id' => 2, 'name' => 'iTunes Gift Card $50', 'price' => 50.00, 'currency' => 'USD'],
            ],
        ];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $result['data']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/products') && $r->method() === 'GET');
    }

    public function test_execute_returns_empty_data_when_no_products(): void
    {
        $expectedResponse = ['status' => true, 'data' => []];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertEmpty($result['data']);
    }

    public function test_execute_handles_api_error(): void
    {
        Http::fake(['*/products' => Http::response(['error' => 'Service unavailable'], 503)]);

        $this->expectException(\Exception::class);

        app(GetProducts::class)->execute('gift2games');
    }

    public function test_execute_handles_unauthorized_error(): void
    {
        Http::fake(['*/products' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->expectException(\Exception::class);

        app(GetProducts::class)->execute('gift2games');
    }

    public function test_execute_with_complete_product_details(): void
    {
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

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals(123, $result['data'][0]['id']);
        $this->assertEquals('PlayStation Store Gift Card $100', $result['data'][0]['name']);
        $this->assertEquals(100.00, $result['data'][0]['price']);
        $this->assertEquals('PSN-100-USD', $result['data'][0]['sku']);
    }

    public function test_execute_handles_network_timeout(): void
    {
        Http::fake(['*/products' => Http::response(null, 408)]);

        $this->expectException(\Exception::class);

        app(GetProducts::class)->execute('gift2games');
    }

    public function test_execute_handles_large_product_list(): void
    {
        $products = array_map(fn ($i) => ['id' => $i, 'name' => "Product $i", 'price' => $i * 10, 'currency' => 'USD'], range(1, 100));
        $expectedResponse = ['status' => true, 'data' => $products];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift2games');

        $this->assertCount(100, $result['data']);
        $this->assertEquals(1, $result['data'][0]['id']);
        $this->assertEquals(100, $result['data'][99]['id']);
    }

    public function test_http_request_uses_get_method(): void
    {
        Http::fake(['*/products' => Http::response(['status' => true, 'data' => []], 200)]);

        app(GetProducts::class)->execute('gift2games');

        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/products'));
    }

    public function test_execute_with_pagination_metadata(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [['id' => 1, 'name' => 'Product 1', 'price' => 25.00]],
            'meta' => ['current_page' => 1, 'total_pages' => 5, 'total_items' => 50, 'per_page' => 10],
        ];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift2games');

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(1, $result['meta']['current_page']);
        $this->assertEquals(5, $result['meta']['total_pages']);
    }

    public function test_execute_uses_eur_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [['id' => 1, 'name' => 'Amazon Gift Card €25', 'price' => 25.00, 'currency' => 'EUR']],
        ];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift-2-games-eur');

        $this->assertEquals($expectedResponse, $result);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/products') && $r->method() === 'GET');
    }

    public function test_execute_uses_gbp_wallet(): void
    {
        $expectedResponse = [
            'status' => true,
            'data' => [['id' => 1, 'name' => 'Amazon Gift Card £25', 'price' => 25.00, 'currency' => 'GBP']],
        ];

        Http::fake(['*/products' => Http::response($expectedResponse, 200)]);

        $result = app(GetProducts::class)->execute('gift-2-games-gbp');

        $this->assertEquals($expectedResponse, $result);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/products') && $r->method() === 'GET');
    }

    public function test_execute_throws_exception_for_unknown_supplier_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: gift-2-games-unknown');

        app(GetProducts::class)->execute('gift-2-games-unknown');
    }
}
