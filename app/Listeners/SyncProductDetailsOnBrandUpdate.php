<?php

namespace App\Listeners;

use App\Events\BrandUpdated;
use App\Repositories\ProductRepository;

class SyncProductDetailsOnBrandUpdate
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function handle(BrandUpdated $event): void
    {
        // Touch the brand's products so each re-syncs (updated brand details) via
        // the single SyncProductToManaStore listener.
        $productIds = $this->productRepository->getProductsByBrandId($event->brand->id)->pluck('id')->all();

        $this->productRepository->touchByIds($productIds);
    }
}
