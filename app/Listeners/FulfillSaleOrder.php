<?php

namespace App\Listeners;

use App\Events\SaleOrderCompleted;

class FulfillSaleOrder
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SaleOrderCompleted $event): void
    {
        $saleOrder = $event->saleOrder;

    }
}
