<?php

namespace Tests\Unit\Actions\Irewardify;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Actions\Irewardify\ExportProductsCsv;

class ExportProductsCsvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.irewardify.url' => 'https://irewardify.test']);

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

    private function productDetailsResponse(string $sku, string $itemId): array
    {
        return [
            'data' => [
                'variants' => [
                    [
                        'sku' => $sku,
                        'item_id' => $itemId,
                        'variant_name' => '$10',
                        'variant_price' => 10.0,
                        'discounted_price' => 9.5,
                    ],
                ],
            ],
        ];
    }

    public function test_it_builds_a_csv_row_per_variant_with_computed_discounts(): void
    {
        Http::fake([
            '*/customer/products/PROD-1' => Http::response($this->productDetailsResponse('SKU-1', 'ITEM-1')),
            '*/customer/products/PROD-2' => Http::response($this->productDetailsResponse('SKU-2', 'ITEM-2')),
            '*/customer/products*' => Http::response([
                'data' => [
                    'items' => [
                        $this->productItem('PROD-1', 'Amazon'),
                        $this->productItem('PROD-2', 'Steam'),
                    ],
                ],
            ]),
        ]);

        $csv = app(ExportProductsCsv::class)->execute();
        $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));

        $this->assertSame([
            'Sr No.', 'Item ID', 'Product ID', 'Product Name', 'Product Description',
            'SKU', 'Brand', 'Region', 'Currency', 'Face Value', 'Cost Price',
            'Discount Amount', 'Discount Percentage', 'Status',
        ], $rows[0]);

        $this->assertSame([
            '1', 'ITEM-1', 'PROD-1', 'Amazon $10', 'A gift card', 'SKU-1', 'Amazon',
            'US', 'usd', '10', '9.5', '0.5', '5', 'active',
        ], $rows[1]);

        $this->assertSame([
            '2', 'ITEM-2', 'PROD-2', 'Steam $10', 'A gift card', 'SKU-2', 'Steam',
            'US', 'usd', '10', '9.5', '0.5', '5', 'active',
        ], $rows[2]);
    }

    public function test_it_skips_products_with_no_variants(): void
    {
        Http::fake([
            '*/customer/products/PROD-1' => Http::response(['data' => ['variants' => []]]),
            '*/customer/products*' => Http::response([
                'data' => [
                    'items' => [
                        $this->productItem('PROD-1', 'Amazon'),
                    ],
                ],
            ]),
        ]);

        $csv = app(ExportProductsCsv::class)->execute();
        $rows = array_values(array_filter(explode("\n", $csv)));

        $this->assertCount(1, $rows);
    }
}
