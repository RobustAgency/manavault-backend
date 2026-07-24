<?php

namespace App\Listeners;

use App\Events\DigitalProductPriorityChange;

class SyncPriceOnDigitalProductPriorityChange
{
    public function handle(DigitalProductPriorityChange $event): void
    {
        // Priority changes which digital product (and price) resolves, so touch the
        // product to recompute status and sync via the single SyncProductToManaStore listener.
        $event->product->touch();
    }
}
