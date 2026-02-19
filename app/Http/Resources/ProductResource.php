<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use App\Enums\Product\FulfillmentMode;
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
            'face_value' => $this->face_value,
            'selling_price' => $this->selling_price,
            'quantity' => $this->getAvailableQuantity(),
            'currency' => $this->currency,
            'status' => $this->status,
            'regions' => $this->regions,
            'is_custom_priority' => $this->fulfillment_mode === FulfillmentMode::MANUAL->value ? true : false,
            'is_out_of_stock' => $this->is_out_of_stock,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'digital_products' => DigitalProductResource::collection($this->whenLoaded('digitalProducts')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
        ];
    }

    private function getAvailableQuantity(): int
    {
        return (int) Voucher::query()
            ->whereIn('purchase_order_item_id', function ($query) {
                $query->select('id')
                    ->from('purchase_order_items')
                    ->whereIn('digital_product_id', function ($subQuery) {
                        $subQuery->select('digital_product_id')
                            ->from('product_supplier')
                            ->where('product_id', $this->resource->id);
                    });
            })
            ->where('status', 'available')
            ->count();
    }
}
