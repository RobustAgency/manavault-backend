<?php

namespace App\Listeners;

use App\Actions\DispatchProductSyncWebhook;
use App\Events\DigitalProductPriorityChange;

class SyncPriceOnDigitalProductPriorityChange
{
    /**
     * Create the event listener.
     */
    public function __construct(private DispatchProductSyncWebhook $dispatchProductSyncWebhooks)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DigitalProductPriorityChange $event): void
    {
        $product = $event->product;
        $this->dispatchProductSyncWebhooks->execute(
            'digital_product.priority_changed',
            [$product->id]
        );
    }
}
