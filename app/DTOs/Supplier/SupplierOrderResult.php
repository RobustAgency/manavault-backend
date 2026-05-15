<?php

namespace App\DTOs\Supplier;

final class SupplierOrderResult
{
    /**
     * @param  VoucherData[]  $vouchers  Populated when isComplete=true; empty for async suppliers.
     */
    public function __construct(
        public readonly ?string $transactionId,
        public readonly bool $isComplete,
        public readonly array $vouchers = [],
    ) {}
}
