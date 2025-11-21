<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
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
            'total_price' => $this->total_price,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'suppliers' => SupplierResource::collection($this->whenLoaded('suppliers')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'vouchers' => VoucherResource::collection($this->whenLoaded('vouchers')),
        ];
    }
}
