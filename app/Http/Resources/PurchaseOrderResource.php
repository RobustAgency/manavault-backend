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
        /* TODO: This is a workaround to avoid N+1 queries when loading purchase order items for each supplier.
        We can do it by creating a pivot table for purchase_order_items and purchase_order_suppliers, but for now this is a simpler solution.*/
        if ($this->relationLoaded('purchaseOrderSuppliers') && $this->relationLoaded('items')) {
            foreach ($this->purchaseOrderSuppliers as $purchaseOrderSupplier) {
                $purchaseOrderSupplier->setRelation(
                    'purchaseOrderItems',
                    $this->items->where('supplier_id', $purchaseOrderSupplier->supplier_id)->values()
                );
            }
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'suppliers' => PurchaseOrderSupplierResource::collection($this->whenLoaded('purchaseOrderSuppliers')),
            'vouchers' => VoucherResource::collection($this->whenLoaded('vouchers')),
        ];
    }
}
