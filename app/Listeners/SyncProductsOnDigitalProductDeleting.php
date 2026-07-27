<?php

namespace App\Listeners;

use App\Events\DigitalProductDeleting;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnDigitalProductDeleting
{
    const EVENT_NAME = 'digital_product.deleted';

    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    /**
     * Resolve affected products before the digital product (and its
     * product_supplier pivot rows) are removed by the delete's cascade.
     */
    public function handle(DigitalProductDeleting $event): void
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
