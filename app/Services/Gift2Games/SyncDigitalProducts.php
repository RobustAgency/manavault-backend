<?php

namespace App\Services\Gift2Games;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Gift2Games\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncDigitalProducts
{
    private const G2G_SUPPLIER_SLUGS = [
        'gift2games',
        'gift-2-games-eur',
        'gift-2-games-gbp',
    ];

    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        foreach (self::G2G_SUPPLIER_SLUGS as $slug) {
            $this->syncForSupplier($slug);
        }
    }

    private function syncForSupplier(string $supplierSlug): void
    {
        $supplier = Supplier::where('slug', $supplierSlug)->first();

        if (! $supplier) {
            Log::warning("Gift2Games sync: supplier not found for slug: {$supplierSlug}");

            return;
        }

        $products = $this->getProducts->execute($supplierSlug);

        if ($products['status'] == 0) {
            Log::error("Failed to sync Gift2Games products for supplier: {$supplierSlug}");

            return;
        }

        $items = $products['data'] ?? [];
        foreach ($items as $item) {
            $this->digitalProductRepository->createOrUpdate([
                'sku' => $item['id'],
                'supplier_id' => $supplier->id,
            ], [
                'supplier_id' => $supplier->id,
                'name' => $item['title'],
                'sku' => $item['id'],
                'brand' => $item['brand'] ?? null,
                'description' => $item['description'] ?? null,
                'cost_price' => $item['price'] ?? null,
                'currency' => strtolower($item['originalCurrency']),
                'metadata' => $item,
                'source' => 'api',
                'last_synced_at' => now(),
            ]);
        }
    }
}
