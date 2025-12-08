<?php

namespace App\Observers;

use App\Models\Product;
use App\Constants\ActivityEvents;
use App\Repositories\ActivityLogRepository;

class ProductObserver
{
    public function __construct(private ActivityLogRepository $activityLogRepository) {}

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_CREATED);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_UPDATED);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->activityLogRepository->createActivityLog($product, $product->id, ActivityEvents::PRODUCT_DELETED);
    }
}
