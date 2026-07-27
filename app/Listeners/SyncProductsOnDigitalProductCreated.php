<?php

namespace App\Listeners;

use App\Events\DigitalProductCreated;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnDigitalProductCreated
{
    const EVENT_NAME = 'digital_product.created';

    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    public function handle(DigitalProductCreated $event): void
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
