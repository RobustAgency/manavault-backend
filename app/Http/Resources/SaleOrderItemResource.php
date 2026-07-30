<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleOrderItem
 */
class SaleOrderItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'status' => $this->status->value,
            'sale_order_number' => $this->whenLoaded('saleOrder', fn () => $this->saleOrder->order_number),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
