<?php

namespace Tests\Unit\Actions\Gift2Games;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Gift2Games\GetProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_matching_product(): void
    {
        Http::fake(['*/products' => Http::response([
            'status' => 1,
            'data' => [
                [
                    'id' => '1325',
                    'categoryId' => '409',
                    'title' => 'PUBG NEW STATE 300 NC',
                    'productType' => 'Voucher',
                    'sellPrice' => 0.94,
                    'price' => 0.94,
                    'inStock' => true,
                    'currency' => 'USD',
                    'productFaceValue' => 0.99,
                    'productFaceValueCurrency' => 'USD',
                ],
            ],
            'metaData' => ['balance' => 25441.132, 'currency' => 'USD'],
        ], 200)]);

        $result = app(GetProduct::class)->execute('gift2games', 1325);

        $this->assertNotNull($result);
        $this->assertEquals('1325', $result['id']);
        $this->assertEquals(0.94, $result['price']);
        $this->assertEquals('PUBG NEW STATE 300 NC', $result['title']);
    }

    public function test_execute_sends_post_request_with_ids_form_body(): void
    {
        Http::fake(['*/products' => Http::response(['status' => 1, 'data' => []], 200)]);

        app(GetProduct::class)->execute('gift2games', 1325);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/products')
                && $request['ids'] === [1325];
        });
    }

    public function test_execute_returns_null_when_status_is_zero(): void
    {
        Http::fake(['*/products' => Http::response(['status' => 0, 'data' => []], 200)]);

        $this->assertNull(app(GetProduct::class)->execute('gift2games', 1325));
    }

    public function test_execute_returns_null_when_requested_id_not_in_response(): void
    {
        Http::fake(['*/products' => Http::response([
            'status' => 1,
            'data' => [['id' => '9999', 'price' => 10.00]],
        ], 200)]);

        $this->assertNull(app(GetProduct::class)->execute('gift2games', 1325));
    }

    public function test_execute_matches_id_regardless_of_string_or_int_type(): void
    {
        Http::fake(['*/products' => Http::response([
            'status' => 1,
            'data' => [['id' => '1325', 'price' => 12.34]],
        ], 200)]);

        $result = app(GetProduct::class)->execute('gift2games', '1325');

        $this->assertNotNull($result);
        $this->assertEquals(12.34, $result['price']);
    }

    public function test_execute_throws_on_api_error(): void
    {
        Http::fake(['*/products' => Http::response(['error' => 'Service unavailable'], 503)]);

        $this->expectException(\Exception::class);

        app(GetProduct::class)->execute('gift2games', 1325);
    }

    public function test_execute_throws_exception_for_unknown_supplier_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Gift2Games supplier slug: gift-2-games-unknown');

        app(GetProduct::class)->execute('gift-2-games-unknown', 1325);
    }
}
