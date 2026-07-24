<?php

namespace App\Listeners;

use App\Events\AssignDigitalProduct;

class SyncProductOnDigitalProductAssignment
{
    public function handle(AssignDigitalProduct $event): void
    {
        // Touching the product recomputes its status and fires the Product `updated`
        // event, which the single SyncProductToManaStore listener syncs to ManaStore.
        $event->product->touch();
    }
}
