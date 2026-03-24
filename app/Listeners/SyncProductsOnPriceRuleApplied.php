<?php

namespace App\Listeners;

use App\Events\PriceRuleApplied;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnPriceRuleApplied
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PriceRuleApplied $event): void
    {
        $productIds = $this->productRepository->getProductIdsByDigitalProductIds($event->digitalProductIds);

        if (empty($productIds)) {
            return;
        }

        $this->dispatchProductSyncWebhook->execute('price_rule.applied', $productIds);
    }
}
