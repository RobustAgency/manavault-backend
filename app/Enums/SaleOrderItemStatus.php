<?php

namespace App\Enums;

enum SaleOrderItemStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * The outbound webhook event name for this status, or null when the status
     * has no associated webhook.
     */
    public function webhookEvent(): ?string
    {
        return match ($this) {
            self::COMPLETED => 'sale_order_item.fulfilled',
            default => null,
        };
    }
}
