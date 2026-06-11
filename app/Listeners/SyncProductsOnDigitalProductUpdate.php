<?php

namespace App\Listeners;

use App\Events\DigitalProductUpdated;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnDigitalProductUpdate
{
    const EVENT_NAME = 'digital_product.updated';

    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    public function handle(DigitalProductUpdated $event): void
    {
        $productIds = $this->productRepository->getProductIdsByDigitalProductId(
            $event->digitalProduct->id
        );

        if (empty($productIds)) {
            return;
        }

        $this->dispatchProductSyncWebhook->execute(self::EVENT_NAME, $productIds);
    }
}
