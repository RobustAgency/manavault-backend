<?php

namespace App\Events;

use App\Models\PurchaseOrderItem;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NewVouchersAvailable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  PurchaseOrderItem  $purchaseOrderItem  The purchase order that added the new vouchers
     */
    public function __construct(
        public readonly PurchaseOrderItem $purchaseOrderItem,
    ) {}
}
