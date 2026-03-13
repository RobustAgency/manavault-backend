<?php

namespace App\Listeners;

use App\Events\DigitalStockUpdate;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnDigitalProductSellingPriceUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook
    ) {}

    /**
     * Handle the event.
     */
    public function handle(DigitalStockUpdate $event): void
    {
        // Sync products with the updated digital product information
        $digitalProduct = $event->digitalProduct;
        $productIds = $this->productRepository->getProductIdsByDigitalProductId($digitalProduct->id);

        $this->dispatchProductSyncWebhook->execute('digital_product.updated', $productIds);
    }
}
