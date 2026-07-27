<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DigitalProductsDeactivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The IDs of the digital products that were deactivated.
     *
     * @param  array<int>  $digitalProductIds
     */
    public function __construct(public array $digitalProductIds) {}
}
