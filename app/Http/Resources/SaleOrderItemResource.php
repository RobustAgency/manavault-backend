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
            'product_variant_id' => $this->product_variant_id,
            'variant_name' => $this->variant_name,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'price' => $this->price,
            'purchase_price' => $this->purchase_price,
            'conversion_fee' => $this->conversion_fee,
            'total_price' => $this->total_price,
            'discount_amount' => $this->discount_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
