<?php

namespace App\Listeners;

use App\Events\DigitalProductUpdated;
use App\Repositories\ProductRepository;

class SyncProductsOnDigitalProductUpdate
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function handle(DigitalProductUpdated $event): void
    {
        // Touch the affected products so each recomputes its status and fires the
        // Product `updated` event, which the single SyncProductToManaStore listener syncs.
        $productIds = $this->productRepository->getProductIdsByDigitalProductId(
            $event->digitalProduct->id
        );

        $this->productRepository->touchByIds($productIds);
    }
}
