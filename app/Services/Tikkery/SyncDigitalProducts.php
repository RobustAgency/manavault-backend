<?php

namespace App\Services\Tikkery;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Tikkery\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncDigitalProducts
{
    private const TIKKERY_SUPPLIER_SLUG = 'tikkery';

    private const PAGE_SIZE = 100;

    private array $syncedSkus = [];

    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', self::TIKKERY_SUPPLIER_SLUG)->firstOrFail();

        $offset = 0;

        do {
            $response = $this->getProducts->execute(self::PAGE_SIZE, $offset);

            $total = $response['total'] ?? 0;
            $items = $response['products'] ?? [];

            if (empty($items)) {
                break;
            }

            $this->syncBatch($supplier, $items);

            $offset += count($items);
        } while ($offset < $total);

        $deactivated = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $this->syncedSkus);

        if ($deactivated > 0) {
            Log::info("Tikkery sync: deactivated {$deactivated} removed product(s)");
        }

        Log::info('Tikkery sync completed. Synced '.count($this->syncedSkus).' digital products.');
    }

    /**
     * Sync a batch of products.
     */
    private function syncBatch(Supplier $supplier, array $items): void
    {
        foreach ($items as $item) {
            try {
                $sku = $item['sku'];

                $this->digitalProductRepository->createOrUpdate(
                    [
                        'sku' => $sku,
                        'supplier_id' => $supplier->id,
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'name' => $item['title'],
                        'sku' => $sku,
                        'brand' => $item['cardBrand']['title'] ?? null,
                        'face_value' => $item['cardValue'] ?? null,
                        'cost_price' => $item['price'],
                        'currency' => strtolower($item['currency'] ?? 'usd'),
                        'regions' => $item['regions'] ?? null,
                        'metadata' => $item,
                        'source' => 'api',
                        'last_synced_at' => now(),
                        'is_active' => true,
                        'in_stock' => true,
                    ]
                );

                $this->syncedSkus[] = $sku;
            } catch (\Throwable $e) {
                Log::error("Tikkery sync: failed to sync SKU {$item['sku']}: {$e->getMessage()}");
            }
        }
    }
}
