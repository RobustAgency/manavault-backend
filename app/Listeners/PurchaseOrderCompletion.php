<?php

namespace App\Listeners;

use App\Events\NewVouchersAvailable;
use App\Events\PurchaseOrderCompleted;

class PurchaseOrderCompletion
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(PurchaseOrderCompleted $event): void
    {
        $purchaseOrder = $event->purchaseOrder;

        foreach ($purchaseOrder->items as $item) {
            event(new NewVouchersAvailable($item));
        }
    }
}
