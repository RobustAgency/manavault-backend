<?php

namespace App\Listeners;

use App\Events\SaleOrderItemUpdated;
use App\Actions\DispatchSaleOrderItemWebhook;

class DispatchSaleOrderItemStatusEvents
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly DispatchSaleOrderItemWebhook $dispatchSaleOrderItemWebhook) {}

    /**
     * Dispatch the outbound item webhook when a sale order item reaches a status that
     * Manastore needs to know about (currently only completed, i.e. every ordered unit
     * has a voucher allocated).
     */
    public function handle(SaleOrderItemUpdated $event): void
    {
        $saleOrderItem = $event->saleOrderItem;

        if (! $saleOrderItem->wasChanged('status')) {
            return;
        }

        // Only some statuses have an outbound webhook; ignore the rest.
        $webhookEvent = $saleOrderItem->status->webhookEvent();
        if ($webhookEvent === null) {
            logger()->debug('SaleOrderItemUpdated: status has no outbound webhook, skipping', [
                'sale_order_item_id' => $saleOrderItem->id,
                'status' => $saleOrderItem->status->value,
            ]);

            return;
        }

        logger()->info('SaleOrderItemUpdated: dispatching sale order item webhook', [
            'sale_order_item_id' => $saleOrderItem->id,
            'status' => $saleOrderItem->status->value,
            'webhook_event' => $webhookEvent,
        ]);

        $this->dispatchSaleOrderItemWebhook->execute($webhookEvent, $saleOrderItem);
    }
}
