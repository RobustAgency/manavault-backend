<?php

namespace App\Suppliers\Support;

final class PlacementResult
{
    /**
     * @param  array<int, VoucherDraft>  $vouchers
     */
    public function __construct(
        public readonly PlacementOutcome $outcome,
        public readonly ?string $transactionId = null,
        public readonly array $vouchers = [],
    ) {}

    /**
     * @param  array<int, VoucherDraft>  $vouchers
     */
    public static function ready(?string $transactionId, array $vouchers): self
    {
        return new self(PlacementOutcome::VOUCHERS_READY, $transactionId, $vouchers);
    }

    public static function awaiting(?string $transactionId): self
    {
        return new self(PlacementOutcome::AWAITING_VOUCHERS, $transactionId);
    }
}
