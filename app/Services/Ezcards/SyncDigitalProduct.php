<?php

namespace App\Services\Ezcards;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Ezcards\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncDigitalProduct
{
    private int $pageSize = 100;

    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    /**
     * Sync all products from EZ Cards API.
     */
    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', 'ez_cards')->firstOrFail();

        $page = 1;

        do {
            $response = $this->getProducts->execute($this->pageSize, $page);
            $data = $response['data'] ?? [];
            $items = $data['items'] ?? [];
            $totalPage = $data['totalPage'] ?? 0;

            if (empty($items)) {
                break;
            }

            $this->syncBatch($supplier, $items);

            $page++;
        } while ($page <= $totalPage);
    }

    private function syncBatch(Supplier $supplier, array $items): void
    {
        foreach ($items as $item) {
            try {
                $this->digitalProductRepository->createOrUpdate(
                    [
                        'sku' => $item['sku'],
                        'supplier_id' => $supplier->id,
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'brand' => $item['brand'] ?? null,
                        'description' => $item['description'] ?? null,
                        'cost_price' => $item['prices'][0]['price'] ?? 0,
                        'metadata' => $item,
                        'source' => 'api',
                        'last_synced_at' => now(),
                    ]
                );

            } catch (\Throwable $e) {
                Log::error("Failed syncing SKU {$item['sku']}: {$e->getMessage()}");
            }
        }
    }
}
