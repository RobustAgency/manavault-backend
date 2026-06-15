<?php

namespace App\Listeners;

use App\Events\NewVouchersAvailable;
use App\Enums\PurchaseOrderItemStatus;
use App\Events\PurchaseOrderItemUpdated;

class DispatchNewVouchersOnFulfillment
{
    public function handle(PurchaseOrderItemUpdated $event): void
    {
        $item = $event->item;

        if (
            $item->wasChanged('status')
            && $item->status === PurchaseOrderItemStatus::FULFILLED
            && $item->digital_product_id !== null
        ) {
            event(new NewVouchersAvailable($item));
        }
    }
}
