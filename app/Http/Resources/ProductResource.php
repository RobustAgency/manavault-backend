<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'name' => $this->name,
            'sku' => $this->sku,
            'brand_id' => $this->brand_id,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'tags' => $this->tags,
            'image' => $this->image,
            'selling_price' => $this->selling_price,
            'status' => $this->status,
            'regions' => $this->regions,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'digital_products' => DigitalProductResource::collection($this->whenLoaded('digitalProducts')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
        ];
    }
}
