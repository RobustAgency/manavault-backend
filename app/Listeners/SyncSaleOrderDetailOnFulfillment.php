<?php

namespace App\Listeners;

use App\Enums\SaleOrderStatus;
use App\Actions\DispatchSaleOrderWebhook;
use App\Events\SaleOrderFulfillmentUpdated;

class SyncSaleOrderDetailOnFulfillment
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly DispatchSaleOrderWebhook $dispatchSaleOrderWebhook) {}

    /**
     * Handle the event.
     */
    public function handle(SaleOrderFulfillmentUpdated $event): void
    {
        $saleOrder = $event->saleOrder;

        $webhookEvent = SaleOrderStatus::from($saleOrder->status)->webhookEvent();

        if ($webhookEvent === null) {
            return;
        }

        $this->dispatchSaleOrderWebhook->execute($webhookEvent, $saleOrder);
    }
}
