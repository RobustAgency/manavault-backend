<?php

namespace App\Events;

use App\Models\Brand;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class BrandUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Brand $brand;

    /**
     * Create a new event instance.
     */
    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }
}
