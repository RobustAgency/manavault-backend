<?php

namespace App\Services\Giftery;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Giftery\GetProductsAction;
use App\Repositories\DigitalProductRepository;

class SyncProducts
{
    public function __construct(
        private GetProductsAction $getProductsAction,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', 'giftery-api')->firstOrFail();

        try {
            $response = $this->getProductsAction->execute();

            $products = $response['data'] ?? [];

            if (empty($products)) {
                Log::info('No products returned from Giftery API');

                return;
            }

            $this->syncBatch($supplier, $products);
        } catch (\Throwable $e) {
            Log::error('Giftery sync error: '.$e->getMessage());
        }
    }

    private function syncBatch(Supplier $supplier, array $products): void
    {
        $syncedSkus = [];

        foreach ($products as $product) {
            try {
                $items = $product['items'] ?? [];

                foreach ($items as $item) {
                    try {
                        $sku = (string) $item['id'];

                        $price = $item['price'] ?? 0;
                        $currency = strtolower($item['rrpCurrency'] ?? 'usd');

                        // Create the digital product name combining product and item name
                        $name = $product['name'].' - '.$item['name'];

                        $this->digitalProductRepository->createOrUpdate(
                            [
                                'sku' => $sku,
                                'supplier_id' => $supplier->id,
                            ],
                            [
                                'supplier_id' => $supplier->id,
                                'name' => $name,
                                'sku' => $sku,
                                'brand' => $product['brand'] ?? null,
                                'description' => $product['description'] ?? null,
                                'face_value' => $item['rrp'] ?? 0,
                                'cost_price' => $price,
                                'currency' => $currency,
                                'region' => $product['country'] ?? null,
                                'metadata' => [
                                    'product_id' => $product['id'],
                                    'item_id' => $item['id'],
                                    'category' => $product['category'] ?? null,
                                    'instruction' => $product['instruction'] ?? null,
                                    'benefits' => $item['benefits'] ?? [],
                                    'direct_order' => $item['directOrder'] ?? false,
                                    'rrp_currency' => $item['rrpCurrency'] ?? null,
                                ],
                                'source' => 'api',
                                'last_synced_at' => now(),
                                'is_active' => true,
                                'in_stock' => $item['inStock'] > 0,
                            ]
                        );

                        $syncedSkus[] = $sku;
                    } catch (\Throwable $e) {
                        Log::error("Failed syncing Giftery item {$item['id']}: {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Error processing Giftery product: '.$e->getMessage());
            }
        }

        // Deactivate products that are no longer in the API response
        $deactivated = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $syncedSkus);

        if ($deactivated > 0) {
            Log::info("Giftery sync: deactivated {$deactivated} removed product(s)");
        }

        Log::info('Giftery sync completed successfully. Synced '.count($syncedSkus).' digital products.');
    }
}
