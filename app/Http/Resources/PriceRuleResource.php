<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PriceRule
 */
class PriceRuleResource extends JsonResource
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
            'description' => $this->description,
            'match_type' => $this->match_type,
            'action_operator' => $this->action_operator,
            'action_mode' => $this->action_mode,
            'action_value' => $this->action_value,
            'status' => $this->status,
            'conditions' => PriceRuleConditionResource::collection($this->whenLoaded('conditions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
