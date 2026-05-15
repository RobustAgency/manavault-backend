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
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => [
                'amount' => (string) $this->unit_price,
                'currency' => $this->currency,
            ],
            'subtotal' => [
                'amount' => (string) $this->subtotal,
                'currency' => $this->currency,
            ],
            'conversion_fee' => [
                'amount' => (string) $this->conversion_fee,
                'currency' => $this->currency,
            ],
            'discount_amount' => [
                'amount' => (string) $this->discount_amount,
                'currency' => $this->currency,
            ],
            'currency' => $this->currency,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
