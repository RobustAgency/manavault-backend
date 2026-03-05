<?php

namespace App\Listeners;

use App\Events\BrandDeleted;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnBrandDeletion
{
    const EVENT_NAME = 'brand.deleted';

    /**
     * Create the event listener.
     */
    public function __construct(
        private DispatchProductSyncWebhook $dispatchProductSyncWebhook,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(BrandDeleted $event): void
    {
        if (empty($event->productIds)) {
            return;
        }

        $this->dispatchProductSyncWebhook->execute(self::EVENT_NAME, $event->productIds);
    }
}
