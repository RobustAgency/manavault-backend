<?php

namespace Tests\Unit\Services\Irewardify;

use Tests\TestCase;
use App\Models\Supplier;
use Illuminate\Support\Sleep;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Http;
use App\Services\Irewardify\SyncProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncProductsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.irewardify.url' => 'https://irewardify.test']);

        $this->supplier = Supplier::factory()->create(['slug' => 'irewardify']);

        cache()->put('irewardify_access_token', 'fake-token', 3600);
    }

    private function productItem(string $id, string $name): array
    {
        return [
            '_id' => $id,
            'name' => $name,
            'currency' => 'USD',
            'image_url' => null,
            'description' => 'A gift card',
            'country' => 'US',
            'status' => 'active',
        ];
    }

    private function productDetailsResponse(string $sku): array
    {
        return [
            'data' => [
                'variants' => [
                    [
                        'sku' => $sku,
                        'variant_name' => '$10',
                        'variant_price' => 10.0,
                        'discounted_price' => 9.5,
                    ],
                ],
            ],
        ];
    }

    public function test_sync_throttles_between_product_detail_calls(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::response($this->productDetailsResponse('SKU-1')),
            '*/customer/products/PROD-2' => Http::response($this->productDetailsResponse('SKU-2')),
            '*/customer/products/PROD-3' => Http::response($this->productDetailsResponse('SKU-3')),
            '*/customer/products*' => Http::response([
                'data' => [
                    'items' => [
                        $this->productItem('PROD-1', 'Amazon'),
                        $this->productItem('PROD-2', 'Steam'),
                        $this->productItem('PROD-3', 'Netflix'),
                    ],
                ],
            ]),
        ]);

        app(SyncProducts::class)->processSyncAllProducts();

        // 3 products: throttle runs before the 2nd and 3rd detail calls only
        Sleep::assertSleptTimes(2);
        Sleep::assertSequence([
            Sleep::for(500)->milliseconds(),
            Sleep::for(500)->milliseconds(),
        ]);

        $this->assertSame(3, DigitalProduct::where('supplier_id', $this->supplier->id)->count());
    }

    public function test_sync_completes_when_a_detail_call_is_rate_limited_mid_run(): void
    {
        Sleep::fake();

        Http::fake([
            '*/customer/products/PROD-1' => Http::response($this->productDetailsResponse('SKU-1')),
            '*/customer/products/PROD-2' => Http::sequence()
                ->push(['success' => false, 'message' => 'Too many requests, please try again after a minute.'], 429)
                ->push($this->productDetailsResponse('SKU-2'), 200),
            '*/customer/products/PROD-3' => Http::response($this->productDetailsResponse('SKU-3')),
            '*/customer/products*' => Http::response([
                'data' => [
                    'items' => [
                        $this->productItem('PROD-1', 'Amazon'),
                        $this->productItem('PROD-2', 'Steam'),
                        $this->productItem('PROD-3', 'Netflix'),
                    ],
                ],
            ]),
        ]);

        app(SyncProducts::class)->processSyncAllProducts();

        // The 429 on PROD-2 was retried in place; all products after it still synced
        $this->assertSame(3, DigitalProduct::where('supplier_id', $this->supplier->id)->count());
        $this->assertNotNull(DigitalProduct::where('sku', 'SKU-2')->first());
        $this->assertNotNull(DigitalProduct::where('sku', 'SKU-3')->first());
    }
}
