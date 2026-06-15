<?php

namespace App\Enums\SaleOrder;

enum Status: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PARTIALLY_FULFILLED = 'partially_fulfilled';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * The outbound webhook event name for this status, or null when the status
     * has no associated fulfillment webhook.
     */
    public function webhookEvent(): ?string
    {
        return match ($this) {
            self::COMPLETED => 'sale_order.completed',
            self::PARTIALLY_FULFILLED => 'sale_order.partially_fulfilled',
            default => null,
        };
    }
}
