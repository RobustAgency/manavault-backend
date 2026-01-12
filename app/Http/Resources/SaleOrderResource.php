<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleOrder
 */
class SaleOrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'currency' => $this->currency,
            'source' => $this->source,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'items' => SaleOrderItemResource::collection($this->items),
        ];
    }
}
