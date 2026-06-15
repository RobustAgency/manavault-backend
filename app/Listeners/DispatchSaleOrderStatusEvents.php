<?php

namespace App\Listeners;

use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderUpdated;
use App\Events\SaleOrderFulfillmentUpdated;

class DispatchSaleOrderStatusEvents
{
    /**
     * Handle the event.
     */
    public function handle(SaleOrderUpdated $event): void
    {
        $saleOrder = $event->saleOrder;

        if (! $saleOrder->wasChanged('status')) {
            return;
        }

        // Only fulfillment statuses have an outbound webhook; ignore the rest.
        if (Status::from($saleOrder->status)->webhookEvent() === null) {
            return;
        }

        event(new SaleOrderFulfillmentUpdated($saleOrder));
    }
}
