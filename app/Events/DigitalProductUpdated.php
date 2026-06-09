<?php

namespace App\Events;

use App\Models\DigitalProduct;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DigitalProductUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DigitalProduct $digitalProduct) {}
}
