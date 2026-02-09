<?php

namespace App\Listeners;

use App\Events\DigitalProductUpdate;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductOnDigitalProductUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct(private DispatchProductSyncWebhook $dispatchProductSyncWebhooks) {}

    public function handle(DigitalProductUpdate $event): void
    {
        $product = $event->product;

        $this->dispatchProductSyncWebhooks->execute(
            'digital_product.changed',
            [$product->id]
        );
    }
}
