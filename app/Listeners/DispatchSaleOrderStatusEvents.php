<?php

namespace App\Listeners;

use App\Enums\SaleOrderStatus;
use App\Events\SaleOrderUpdated;
use App\Actions\DispatchSaleOrderWebhook;

class DispatchSaleOrderStatusEvents
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly DispatchSaleOrderWebhook $dispatchSaleOrderWebhook) {}

    /**
     * Dispatch the outbound sale order webhook when a status change reaches a
     * fulfillment milestone (completed or partially fulfilled). The milestone is
     * derived from the sale order's status.
     */
    public function handle(SaleOrderUpdated $event): void
    {
        $saleOrder = $event->saleOrder;

        if (! $saleOrder->wasChanged('status')) {
            logger()->debug('SaleOrderUpdated: status unchanged, skipping webhook', [
                'sale_order_id' => $saleOrder->id,
                'status' => $saleOrder->status,
            ]);

            return;
        }

        // Only fulfillment statuses have an outbound webhook; ignore the rest.
        $webhookEvent = SaleOrderStatus::from($saleOrder->status)->webhookEvent();
        if ($webhookEvent === null) {
            logger()->debug('SaleOrderUpdated: status has no outbound webhook, skipping', [
                'sale_order_id' => $saleOrder->id,
                'status' => $saleOrder->status,
            ]);

            return;
        }

        logger()->info('SaleOrderUpdated: dispatching sale order webhook', [
            'sale_order_id' => $saleOrder->id,
            'status' => $saleOrder->status,
            'webhook_event' => $webhookEvent,
        ]);

        $this->dispatchSaleOrderWebhook->execute($webhookEvent, $saleOrder);
    }
}
