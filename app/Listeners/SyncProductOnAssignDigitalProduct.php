<?php

namespace App\Listeners;

use App\Events\DigitalProductAssigned;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductOnAssignDigitalProduct
{
    public function __construct(private DispatchProductSyncWebhook $dispatchProductSyncWebhooks) {}

    /**
     * Handle the event.
     */
    public function handle(DigitalProductAssigned $event): void
    {
        $product = $event->product;

        $this->dispatchProductSyncWebhooks->execute(
            'digital_product.assigned',
            [$product->id]
        );

    }
}
