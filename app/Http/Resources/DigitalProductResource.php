<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\DigitalProduct;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DigitalProduct
 */
class DigitalProductResource extends JsonResource
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
            'name' => $this->name,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'description' => $this->description,
            'tags' => $this->tags,
            'region' => $this->region,
            'cost_price' => $this->cost_price,
            'metadata' => $this->metadata,
            'last_synced_at' => $this->last_synced_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
        ];
    }
}
