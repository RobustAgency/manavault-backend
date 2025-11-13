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
            'brand' => $this->brand,
            'description' => $this->description,
            'tags' => $this->tags,
            'image' => $this->image,
            'cost_price' => $this->cost_price,
            'status' => $this->status,
            'regions' => $this->regions,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
        ];
    }
}
