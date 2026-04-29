<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NewVouchersAvailable
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $digitalProductIds  IDs of digital products that received new vouchers
     * @param  int  $purchaseOrderId  The purchase order that added the new vouchers
     * @param  int|null  $saleOrderId  The sale order that triggered the purchase order (if any)
     */
    public function __construct(
        public readonly array $digitalProductIds,
        public readonly int $purchaseOrderId,
        public readonly ?int $saleOrderId = null,
    ) {}
}
