<?php

namespace App\Listeners;

use App\Events\BrandUpdated;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductDetailsOnBrandUpdate
{
    const EVENT_NAME = 'brand.updated';

    /**
     * Create the event listener.
     */
    public function __construct(
        private ProductRepository $productRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhooks,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(BrandUpdated $event): void
    {
        $brand = $event->brand;

        $productIds = $this->productRepository->getProductsByBrandId($brand->id)->pluck('id')->all();

        $this->dispatchProductSyncWebhooks->execute(self::EVENT_NAME, $productIds);

    }
}
