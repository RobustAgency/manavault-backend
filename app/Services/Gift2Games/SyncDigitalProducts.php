<?php

namespace App\Services\Gift2Games;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Gift2Games\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncDigitalProducts
{
    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {}

    public function processSyncAllProducts(): void
    {
        $supplier = Supplier::where('slug', 'gift2games')->first();
        $products = $this->getProducts->execute();
        if ($products['status'] == 0) {
            Log::error('Failed to sync Gift2Games products');

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
                'currency' => $item['currency'] ?? null,
            ]);
        }

    }
}
