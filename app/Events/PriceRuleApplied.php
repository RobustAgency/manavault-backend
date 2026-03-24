<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class PriceRuleApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The IDs of the digital products affected by the price rule.
     *
     * @param  array<int>  $digitalProductIds
     */
    public function __construct(public array $digitalProductIds) {}
}
