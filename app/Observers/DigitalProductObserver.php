<?php

namespace App\Observers;

use App\Models\DigitalProduct;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class DigitalProductObserver
{
    public function __construct(
        private ProductRepository $repository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook

    ) {}

    public function updated(DigitalProduct $digitalProduct): void
    {
        $productIds = $this->repository->getProductIdsByDigitalProductIds([$digitalProduct->id]);

        if (! empty($productIds)) {
            $this->dispatchProductSyncWebhook->execute('digital_product.updated', $productIds);
        }
    }
}
