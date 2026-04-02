<?php

namespace App\Http\Resources\ManaStore\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleOrderItem
 */
class SaleOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->sale_order_id,
            'product_variant_id' => $this->product_variant_id,
            'variant_name' => $this->variant_name,
            'product_name' => $this->product_name,
            'purchase_price' => [
                'amount' => (string) $this->purchase_price,
                'currency' => $this->currency,
            ],
            'price' => [
                'amount' => (string) $this->price,
                'currency' => $this->currency,
            ],
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'conversion_fee' => [
                'amount' => (string) $this->conversion_fee,
                'currency' => $this->currency,
            ],
            'total_price' => [
                'amount' => (string) $this->total_price,
                'currency' => $this->currency,
            ],
            'discount_amount' => [
                'amount' => (string) $this->discount_amount,
                'currency' => $this->currency,
            ],
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
