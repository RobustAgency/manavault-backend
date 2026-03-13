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
        $syncedSkus = [];

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
                'cost_price' => $item['originalPrice'] ?? null,
                'currency' => strtolower($item['productFaceValueCurrency'] ?? null),
                'metadata' => $item,
                'source' => 'api',
                'last_synced_at' => now(),
                'is_active' => true,
                'in_stock' => $item['inStock'] ?? false,
            ]);

            $syncedSkus[] = (string) $item['id'];
        }

        $deactivated = $this->digitalProductRepository->deactivateStaleBySupplierId($supplier->id, $syncedSkus);

        if ($deactivated > 0) {
            Log::info("Gift2Games sync: deactivated {$deactivated} removed product(s) for supplier: {$supplierSlug}");
        }
    }
}
