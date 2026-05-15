<?php

namespace App\DTOs\Supplier;

final class VoucherFetchResult
{
    /**
     * @param  VoucherData[]  $vouchers
     * @param  bool  $isPending  true when the supplier has not yet fulfilled the order
     */
    public function __construct(
        public readonly array $vouchers,
        public readonly bool $isPending,
    ) {}
}
