<?php

namespace App\Listeners;

use App\Repositories\ProductRepository;
use App\Events\DigitalProductsDeactivated;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnDigitalProductsDeactivated
{
    const EVENT_NAME = 'digital_product.deactivated';

    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    public function handle(DigitalProductsDeactivated $event): void
    {
        $productIds = $this->productRepository->getProductIdsByDigitalProductIds(
            $event->digitalProductIds
        );

        if (empty($productIds)) {
            return;
        }

        $this->dispatchProductSyncWebhook->execute(self::EVENT_NAME, $productIds);
    }
}
