<?php

namespace App\DTOs;

use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;

final class SupplierOrderResult
{
    public function __construct(
        private readonly PurchaseOrderSupplierStatus $status,
        private readonly ?PurchaseOrderSupplier $order = null,
    ) {}

    /**
     * Order fulfilled synchronously — vouchers already persisted.
     */
    public static function completed(PurchaseOrderSupplier $order): self
    {
        return new self(
            status: PurchaseOrderSupplierStatus::COMPLETED,
            order: $order,
        );
    }

    /**
     * Order accepted but not yet fulfilled (async polling / webhook).
     */
    public static function processing(): self
    {
        return new self(status: PurchaseOrderSupplierStatus::PROCESSING);
    }

    /**
     * Order rejected or failed at the supplier level.
     */
    public static function failed(): self
    {
        return new self(status: PurchaseOrderSupplierStatus::FAILED);
    }

    public function getStatus(): PurchaseOrderSupplierStatus
    {
        return $this->status;
    }

    /** True only when vouchers were received and persisted in the same request. */
    public function isCompleted(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::PROCESSING;
    }

    public function isFailed(): bool
    {
        return $this->status === PurchaseOrderSupplierStatus::FAILED;
    }

    /**
     * Returns the hydrated PurchaseOrderSupplier.
     */
    public function getOrder(): ?PurchaseOrderSupplier
    {
        return $this->order;
    }
}
