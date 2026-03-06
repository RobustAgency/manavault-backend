<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderSupplier
 */
class PurchaseOrderSupplierResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('purchaseOrderItems')),
        ];
    }
}
