<?php

namespace App\DTOs\Supplier;

final class VoucherData
{
    public function __construct(
        public readonly string $code,
        public readonly int $purchaseOrderItemId,
        public readonly ?string $serialNumber = null,
        public readonly ?string $pinCode = null,
    ) {}
}
