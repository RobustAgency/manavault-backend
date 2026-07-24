<?php

namespace App\Listeners;

use App\Events\PriceRuleApplied;
use App\Repositories\ProductRepository;

class SyncProductsOnPriceRuleApplied
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function handle(PriceRuleApplied $event): void
    {
        // Touch the affected products so each recomputes its status and syncs via
        // the single SyncProductToManaStore listener.
        $productIds = $this->productRepository->getProductIdsByDigitalProductIds($event->digitalProductIds);

        $this->productRepository->touchByIds($productIds);
    }
}
