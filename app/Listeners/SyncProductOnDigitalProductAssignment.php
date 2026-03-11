<?php

namespace App\Listeners;

use App\Events\AssignDigitalProduct;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductOnDigitalProductAssignment
{
    /**
     * Create the event listener.
     */
    public function __construct(private DispatchProductSyncWebhook $dispatchProductSyncWebhooks) {}

    public function handle(AssignDigitalProduct $event): void
    {
        $product = $event->product;

        $this->dispatchProductSyncWebhooks->execute(
            'digital_product.changed',
            [$product->id]
        );
    }
}
