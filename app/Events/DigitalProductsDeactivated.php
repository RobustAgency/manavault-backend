<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DigitalProductsDeactivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $digitalProductIds
     */
    public function __construct(public array $digitalProductIds) {}
}
