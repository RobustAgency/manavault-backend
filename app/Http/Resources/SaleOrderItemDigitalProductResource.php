<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleOrderItemDigitalProduct
 */
class SaleOrderItemDigitalProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'digital_product_id' => $this->digital_product_id,
            'quantity_deducted' => $this->quantity_deducted,
        ];
    }
}
