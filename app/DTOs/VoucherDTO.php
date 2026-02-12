<?php

namespace App\DTOs;

/**
 * Data Transfer Object for Voucher
 *
 * Normalizes voucher data regardless of source (file import or array input).
 * Ensures consistent structure across both import methods.
 */
class VoucherDTO
{
    public function __construct(
        public int $digital_product_id,
        public string $code,
        public ?string $serial_number = null,
        public ?string $pin_code = null,
    ) {}

    /**
     * Create a VoucherDTO from file import row data
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromFileRow(array $row): self
    {
        return new self(
            digital_product_id: (int) $row['digital_product_id'],
            code: (string) $row['code'],
            serial_number: $row['serial_number'] ?? null,
            pin_code: $row['pin_code'] ?? null,
        );
    }

    /**
     * Create a VoucherDTO from array input (API request)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArrayInput(array $data): self
    {
        return new self(
            digital_product_id: (int) $data['digital_product_id'],
            code: (string) $data['code'],
            serial_number: $data['serial_number'] ?? null,
            pin_code: $data['pin_code'] ?? null,
        );
    }

    /**
     * Convert DTO to database insert array
     */
    public function toVoucherArray(int $purchaseOrderID, int $purchaseOrderItemID): array
    {
        return [
            'purchase_order_id' => $purchaseOrderID,
            'purchase_order_item_id' => $purchaseOrderItemID,
            'code' => $this->code,
            'serial_number' => $this->serial_number,
            'pin_code' => $this->pin_code,
            'status' => 'available',
        ];
    }
}
