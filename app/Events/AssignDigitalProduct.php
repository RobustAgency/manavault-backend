<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class AssignDigitalProduct
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The product instance.
     */
    public function __construct(public Product $product) {}
}
