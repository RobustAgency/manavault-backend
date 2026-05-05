<?php

namespace App\Suppliers\Support;

final class VoucherDraft
{
    /**
     * @param  array<string, string|int|null>  $dedupeBy  Column => value pairs used by VoucherWriter to skip duplicates within the same purchase order.
     */
    public function __construct(
        public readonly int $purchaseOrderItemId,
        public readonly int $digitalProductId,
        public readonly ?string $code = null,
        public readonly ?string $pin = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $stockId = null,
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly array $dedupeBy = [],
    ) {}
}
