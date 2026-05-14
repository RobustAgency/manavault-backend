<?php

namespace App\Suppliers\Support;

final class PollResult
{
    /**
     * @param  array<int, VoucherDraft>  $vouchers
     */
    public function __construct(
        public readonly bool $skipped,
        public readonly array $vouchers = [],
        public readonly ?string $reason = null,
    ) {}

    /**
     * @param  array<int, VoucherDraft>  $vouchers
     */
    public static function withVouchers(array $vouchers): self
    {
        return new self(skipped: false, vouchers: $vouchers);
    }

    public static function skipped(string $reason): self
    {
        return new self(skipped: true, reason: $reason);
    }
}
