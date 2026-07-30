<?php

namespace App\Actions\Irewardify;

use Illuminate\Support\Facades\Log;

class ExportProductsCsv
{
    /**
     * @var array<int, string>
     */
    private const COLUMNS = [
        'Sr No.',
        'Item ID',
        'Product ID',
        'Product Name',
        'Product Description',
        'SKU',
        'Brand',
        'Region',
        'Currency',
        'Face Value',
        'Cost Price',
        'Discount Amount',
        'Discount Percentage',
        'Status',
    ];

    public function __construct(
        private GetProducts $getProducts,
        private GetProductDetails $getProductDetails,
    ) {}

    public function execute(): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV generation.');
        }

        fputcsv($stream, self::COLUMNS);

        $products = $this->getProducts->execute();
        $productsData = $products['data'] ?? $products;
        $items = $productsData['items'] ?? $productsData;

        if (empty($items)) {
            Log::warning('Irewardify export: no products returned from API.');
        }

        $srNo = 0;

        foreach ($items as $item) {
            $productId = (string) ($item['_id'] ?? '');

            if (! $productId) {
                Log::warning('Irewardify export: skipping product with no ID.', ['name' => $item['name'] ?? 'unknown']);

                continue;
            }

            $productDetails = $this->getProductDetails->execute($productId);
            $productDetailsData = $productDetails['data'] ?? $productDetails;
            $variants = $productDetailsData['variants'] ?? [];

            if (empty($variants)) {
                Log::warning("Irewardify export: product {$productId} ({$item['name']}) has no variants, skipping.");

                continue;
            }

            $currency = strtolower($item['currency'] ?? 'usd');
            $description = $item['description'] ?? null;
            $region = $item['country'] ?? null;
            $brand = $item['name'] ?? null;
            $status = $item['status'] ?? null;

            foreach ($variants as $variant) {
                $variantSku = (string) ($variant['sku'] ?? '');

                if (! $variantSku) {
                    Log::warning("Irewardify export: skipping variant with no SKU for product ID {$productId}.");

                    continue;
                }

                $itemId = $variant['item_id'] ?? null;
                $faceValue = (float) ($variant['variant_price'] ?? 0);
                $costPrice = round((float) ($variant['discounted_price'] ?? $faceValue), 2, PHP_ROUND_HALF_UP);
                $discountAmount = round($faceValue - $costPrice, 2, PHP_ROUND_HALF_UP);
                $discountPercentage = $faceValue > 0
                    ? round(($discountAmount / $faceValue) * 100, 2, PHP_ROUND_HALF_UP)
                    : 0;
                $productName = trim(($item['name'] ?? '').' '.($variant['variant_name'] ?? ''));

                $srNo++;

                fputcsv($stream, [
                    $srNo,
                    $itemId,
                    $productId,
                    $productName,
                    $description,
                    $variantSku,
                    $brand,
                    $region,
                    $currency,
                    $faceValue,
                    $costPrice,
                    $discountAmount,
                    $discountPercentage,
                    $status,
                ]);
            }
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            throw new \RuntimeException('Unable to read generated CSV content.');
        }

        return $content;
    }
}
