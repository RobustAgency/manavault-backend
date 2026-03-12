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
     */
    public function __construct(
        public readonly array $digitalProductIds,
    ) {}
}
