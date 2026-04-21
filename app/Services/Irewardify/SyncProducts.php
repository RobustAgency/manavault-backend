<?php

namespace App\Services\Irewardify;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Irewardify\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncProducts
{
    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', 'irewardify')->firstOrFail();

        $products = $this->getProducts->execute();

        $productsData = $products['data'] ?? $products;

        $items = $productsData['items'] ?? $productsData;

        if (empty($items)) {
            Log::warning('Irewardify sync: no products returned from API.');

            return;
        }

        $syncedSkus = [];

        foreach ($items as $item) {

            $productId = (string) ($item['_id'] ?? '');

            if (! $productId) {
                Log::warning('Irewardify sync: skipping product with no ID.', ['name' => $item['name'] ?? 'unknown']);

                continue;
            }

            $variants = $item['variants'] ?? [];

            if (empty($variants)) {
                Log::warning("Irewardify sync: product {$productId} ({$item['name']}) has no variants, skipping.");

                continue;
            }

            $costPercent = (float) ($item['cost'] ?? 0);
            $currency = strtolower($item['currency'] ?? 'usd');
            $imageUrl = $item['image_url'] ?? null;
            $description = $item['description'] ?? null;
            $country = $item['country'] ?? null;
            $brand = $item['name'] ?? null;
            $isActive = ($item['status'] ?? 'active') === 'active' && ! ($item['archive'] ?? false);

            foreach ($variants as $variant) {
                $variantSku = (string) ($variant['sku'] ?? '');

                if (! $variantSku) {
                    Log::warning("Irewardify sync: skipping variant with no SKU for product ID {$productId}.");

                    continue;
                }

                try {
                    $price = $variant['variant_price'] ?? null;

                    $this->digitalProductRepository->createOrUpdate(
                        [
                            'sku' => $variantSku,
                            'supplier_id' => $supplier->id,
                        ],
                        [
                            'supplier_id' => $supplier->id,
                            'name' => $item['name'].' '.$variant['variant_name'],
                            'sku' => $variantSku,
                            'brand' => $brand,
                            'description' => $description,
                            'face_value' => $price,
                            'cost_price' => $price,
                            'currency' => $currency,
                            'region' => $country,
                            'image_url' => $imageUrl,
                            'metadata' => [
                                'variant' => array_merge($variant, ['product_id' => $productId]),
                            ],
                            'source' => 'api',
                            'last_synced_at' => now(),
                            'is_active' => $isActive,
                            'in_stock' => true,
                        ]
                    );

                    $syncedSkus[] = $variantSku;
                } catch (\Throwable $e) {
                    Log::error("Irewardify sync: failed syncing variant SKU {$variantSku} for product ID {$productId}: {$e->getMessage()}");
                }
            }
        }

        $deactivated = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $syncedSkus);

        if ($deactivated > 0) {
            Log::info("Irewardify sync: deactivated {$deactivated} removed product(s).");
        }
    }
}
