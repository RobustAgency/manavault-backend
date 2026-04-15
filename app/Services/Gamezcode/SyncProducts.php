<?php

namespace App\Services\Gamezcode;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Gamezcode\GetProductsAction;
use App\Repositories\DigitalProductRepository;

class SyncProducts
{
    public function __construct(
        private GetProductsAction $getProductsAction,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', 'gamezcode')->firstOrFail();

        try {
            $products = $this->getProductsAction->execute();

            if (empty($products)) {
                Log::info('No products returned from Gamezcode API');

                return;
            }

            $this->syncBatch($supplier, $products);
        } catch (\Throwable $e) {
            Log::error('Gamezcode sync error: '.$e->getMessage());
        }
    }

    private function syncBatch(Supplier $supplier, array $products): void
    {
        $syncedSkus = [];

        foreach ($products as $product) {
            try {
                // The EAN acts as the unique SKU for each product in Kalixo
                $sku = (string) ($product['ean'] ?? $product['id']);

                // Use the english language entry for the primary name if available
                $englishLang = null;
                foreach (($product['languages'] ?? []) as $lang) {
                    if (is_array($lang) && ($lang['languageCode'] ?? '') === 'en') {
                        $englishLang = $lang;
                        break;
                    }
                }

                $name = $englishLang['name'] ?? $product['name'] ?? $sku;

                // Kalixo returns prices as integers in the smallest currency unit (e.g. pence)
                // Store as-is; the currency field carries the denomination
                $costPrice = $product['buyingPrice'] ?? $product['price'] ?? 0;
                $currency = strtolower($product['buyingCurrencyCode'] ?? $product['currencyCode'] ?? 'gbp');
                $rrp = $product['rrp'] ?? 0;

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
                        'description' => $englishLang['description'] ?? null,
                        'face_value' => $rrp,
                        'cost_price' => $costPrice,
                        'currency' => $currency,
                        'region' => $product['countryCode'] ?? null,
                        'metadata' => [
                            'kalixo_id' => $product['id'] ?? null,
                            'ean' => $product['ean'] ?? null,
                            'kalixo_sku' => $englishLang['sku'] ?? null,
                            'image' => $englishLang['image'] ?? $product['image'] ?? null,
                            'platform' => $product['platform'] ?? null,
                            'main_category' => $product['mainCategory'] ?? null,
                            'sub_category' => $product['subCategory'] ?? null,
                            'product_type' => $product['productType'] ?? null,
                            'tags' => $product['tags'] ?? null,
                            'rrp_currency' => $product['rrpCurrency'] ?? null,
                            'redemption_instructions' => $englishLang['redemptionInstructions'] ?? null,
                            'tnc' => $englishLang['tnc'] ?? null,
                        ],
                        'source' => 'api',
                        'last_synced_at' => now(),
                        'is_active' => ($product['status'] ?? '') === 'active' && ($product['state'] ?? '') === 'live',
                        'in_stock' => true, // Kalixo does not expose per-product stock counts in the catalog
                    ]
                );

                $syncedSkus[] = $sku;
            } catch (\Throwable $e) {
                $id = $product['id'] ?? 'unknown';
                Log::error("Failed syncing Gamezcode product {$id}: {$e->getMessage()}");
            }
        }

        // Deactivate products that are no longer returned by the API
        $deactivated = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $syncedSkus);

        if ($deactivated > 0) {
            Log::info("Gamezcode sync: deactivated {$deactivated} removed product(s)");
        }

        Log::info('Gamezcode sync completed successfully. Synced '.count($syncedSkus).' digital products.');
    }
}
