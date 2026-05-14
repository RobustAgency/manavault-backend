<?php

namespace App\Suppliers\Support;

use App\Models\PurchaseOrder;

final class PollIterationResult
{
    public function __construct(
        public readonly PollOutcome $outcome,
        public readonly PurchaseOrder $purchaseOrder,
        public readonly int $vouchersAdded = 0,
        public readonly ?string $reason = null,
        public readonly ?\Throwable $error = null,
    ) {}

    public static function skipped(PurchaseOrder $purchaseOrder, ?string $reason): self
    {
        return new self(PollOutcome::SKIPPED, $purchaseOrder, reason: $reason);
    }

    public static function processed(PurchaseOrder $purchaseOrder, int $vouchersAdded): self
    {
        return new self(PollOutcome::PROCESSED, $purchaseOrder, vouchersAdded: $vouchersAdded);
    }

    public static function failed(PurchaseOrder $purchaseOrder, \Throwable $error): self
    {
        return new self(PollOutcome::FAILED, $purchaseOrder, error: $error);
    }
}
