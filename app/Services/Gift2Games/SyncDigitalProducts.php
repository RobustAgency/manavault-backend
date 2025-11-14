<?php

namespace App\Services\Gift2Games;

use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Actions\Gift2Games\GetProducts;
use App\Repositories\DigitalProductRepository;

class SyncDigitalProducts
{
    private Supplier $supplier;

    public function __construct(
        private GetProducts $getProducts,
        private DigitalProductRepository $digitalProductRepository,
    ) {
        $this->supplier = Supplier::where('slug', 'gift2games')->first();
    }

    public function processSyncAllProducts(): void
    {
        $products = $this->getProducts->execute();
        if ($products['status'] == 0) {
            Log::error('Failed to sync Gift2Games products');

            return;
        }

        $items = $products['data'] ?? [];
        foreach ($items as $item) {
            $this->digitalProductRepository->createOrUpdate([
                'sku' => $item['id'],
                'supplier_id' => $this->supplier->id,
            ], [
                'supplier_id' => $this->supplier->id,
                'name' => $item['title'],
                'sku' => $item['id'],
                'brand' => $item['brand'] ?? null,
                'description' => $item['description'] ?? null,
                'cost_price' => $item['price'] ?? null,
                'currency' => $item['currency'] ?? null,
                'status' => $item['inStock'] ?? true ? 'active' : 'inactive',
            ]);
        }

    }
}
