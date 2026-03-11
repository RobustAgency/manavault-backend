<?php

namespace App\Observers;

use App\Models\Product;
use App\Constants\ActivityEvents;
use App\Actions\DispatchProductSyncWebhook;
use App\Repositories\ActivityLogRepository;

class ProductObserver
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook
    ) {}

    public function created(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_CREATED);
        $this->dispatchProductSyncWebhook->execute(ActivityEvents::PRODUCT_CREATED, [$product->id]);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_UPDATED);
        $this->dispatchProductSyncWebhook->execute(ActivityEvents::PRODUCT_UPDATED, [$product->id]);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_DELETED);
        $this->dispatchProductSyncWebhook->execute(ActivityEvents::PRODUCT_DELETED, [$product->id]);
    }
}
