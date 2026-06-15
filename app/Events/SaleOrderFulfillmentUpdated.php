<?php

namespace App\Events;

use App\Models\SaleOrder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * Fired when a sale order reaches a fulfillment milestone (completed or partially
 * fulfilled). The exact milestone is derived from the sale order's status by the
 * listener, so a single event covers both cases.
 */
class SaleOrderFulfillmentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SaleOrder $saleOrder
    ) {}
}
