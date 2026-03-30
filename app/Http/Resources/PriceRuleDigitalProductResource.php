<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PriceRuleDigitalProduct
 */
class PriceRuleDigitalProductResource extends JsonResource
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
            'digital_product_id' => $this->digital_product_id,
            'price_rule_id' => $this->price_rule_id,
            'original_selling_price' => $this->original_selling_price,
            'base_value' => $this->base_value,
            'action_mode' => $this->action_mode,
            'action_operator' => $this->action_operator,
            'action_value' => $this->action_value,
            'calculated_price' => $this->calculated_price,
            'final_selling_price' => $this->final_selling_price,
            'applied_at' => $this->applied_at,
            'digital_product' => new DigitalProductResource($this->whenLoaded('digitalProduct')),
            'price_rule' => new PriceRuleResource($this->whenLoaded('priceRule')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
