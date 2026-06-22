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
            $saleOrderId = $item->purchaseOrder->sale_order_id ?? null;

            event(new NewVouchersAvailable(
                digitalProductIds: [$item->digital_product_id],
                purchaseOrderId: $item->purchase_order_id,
                saleOrderId: $saleOrderId,
            ));
        }
    }
}
