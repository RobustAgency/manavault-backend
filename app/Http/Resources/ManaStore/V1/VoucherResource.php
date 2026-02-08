<?php

namespace App\Http\Resources\ManaStore\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Voucher
 */
class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array for ManaStore API.
     *
     * Focused on voucher code delivery for customers.
     * Excludes sensitive internal data like purchase_order details.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'serial_number' => $this->serial_number,
            'pin_code' => $this->pin_code,
        ];
    }
}
