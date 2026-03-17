<?php

namespace App\Listeners;

use App\Events\SaleOrderCompleted;
use App\Actions\DispatchSaleOrderWebhook;

class SyncSaleOrderDetailOnFulfillment
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly DispatchSaleOrderWebhook $dispatchSaleOrderWebhook) {}

    /**
     * Handle the event.
     */
    public function handle(SaleOrderCompleted $event): void
    {
        $saleOrder = $event->saleOrder;
        $this->dispatchSaleOrderWebhook->execute('sale_order.completed', $saleOrder);
    }
}
