<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
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
            'purchase_order_id' => $this->purchase_order_id,
            'digital_product_id' => $this->digital_product_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'digital_product' => new DigitalProductResource($this->whenLoaded('digitalProduct')),
        ];
    }
}
