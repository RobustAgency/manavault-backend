<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use App\DTOs\VoucherDTO;
use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class VoucherPurchaseOrderValidator
{
    /**
     * Validate voucher DTOs against purchase order
     *
     * @param  array<int, VoucherDTO>  $voucherDTOs
     */
    public function validateVoucherDTOs(array $voucherDTOs, int $purchaseOrderId): PurchaseOrder
    {
        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::with('items.digitalProduct')->find($purchaseOrderId);

        if (! $purchaseOrder) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order not found.',
            ]);
        }

        $this->validateNoExistingVouchers($purchaseOrder, $voucherDTOs);
        $this->validateVoucherProductsDTO($purchaseOrder, $voucherDTOs);
        $this->validateVoucherQuantitiesDTO($purchaseOrder, $voucherDTOs);

        return $purchaseOrder;
    }

    /**
     * Validate that all voucher digital products exist in the purchase order (DTO version)
     * AND that all purchase order items with internal suppliers have corresponding vouchers provided
     *
     * @param  array<int, VoucherDTO>  $voucherDTOs
     */
    private function validateVoucherProductsDTO(PurchaseOrder $purchaseOrder, array $voucherDTOs): void
    {
        // Only validate against items with internal suppliers
        $purchaseProductIds = collect($purchaseOrder->items)
            ->filter(fn ($item) => $item->digitalProduct->supplier->type === SupplierType::INTERNAL->value)
            ->pluck('digital_product_id')
            ->toArray();

        $voucherProductIds = collect($voucherDTOs)
            ->pluck('digital_product_id')
            ->toArray();

        $invalidVoucherIds = array_diff($voucherProductIds, $purchaseProductIds);

        if (! empty($invalidVoucherIds)) {
            throw ValidationException::withMessages([
                'voucher_codes' => 'Some voucher digital products do not exist in the purchase order with internal suppliers.',
            ]);
        }

        // Validate: all purchase order items with internal suppliers have vouchers
        $missingVoucherIds = array_diff($purchaseProductIds, $voucherProductIds);

        if (! empty($missingVoucherIds)) {
            throw ValidationException::withMessages([
                'voucher_codes' => 'All digital products with internal suppliers in the purchase order must have voucher codes.',
            ]);
        }
    }

    /**
     * @param  array<int, VoucherDTO>  $voucherDTOs
     */
    private function validateVoucherQuantitiesDTO(PurchaseOrder $purchaseOrder, array $voucherDTOs): void
    {
        $countByDigitalProductId = collect($voucherDTOs)
            ->groupBy('digital_product_id')
            ->map(fn ($group) => $group->count());

        // Only validate quantities for items with internal suppliers
        $internalSupplierItems = $purchaseOrder->items
            ->filter(fn ($item) => $item->digitalProduct->supplier->type === SupplierType::INTERNAL->value);

        foreach ($internalSupplierItems as $item) {
            $digitalProductId = $item->digital_product_id;
            $digitalProductQuantity = $item->quantity;
            $incomingDigitalProductQuantity = $countByDigitalProductId->get($digitalProductId, 0);

            if ($incomingDigitalProductQuantity !== $digitalProductQuantity) {
                throw ValidationException::withMessages([
                    'voucher_codes' => "Digital product ID {$digitalProductId} must have exactly {$digitalProductQuantity} voucher codes, {$incomingDigitalProductQuantity} given.",
                ]);
            }
        }
    }

    /**
     * Validate that no vouchers already exist for the digital products with internal suppliers in this purchase order
     *
     * @param  array<int, VoucherDTO>  $voucherDTOs
     */
    private function validateNoExistingVouchers(PurchaseOrder $purchaseOrder, array $voucherDTOs): void
    {
        // Get unique digital product IDs from the incoming DTOs
        $incomingProductIds = collect($voucherDTOs)
            ->pluck('digital_product_id')
            ->unique()
            ->toArray();

        // Filter to only include digital products with internal suppliers
        $internalSupplierProductIds = collect($purchaseOrder->items)
            ->filter(function ($item) use ($incomingProductIds) {
                return in_array($item->digital_product_id, $incomingProductIds) &&
                       $item->digitalProduct->supplier->type === SupplierType::INTERNAL->value;
            })
            ->pluck('id')
            ->toArray();

        // If no internal supplier products, no need to check for duplicates
        if (empty($internalSupplierProductIds)) {
            return;
        }

        // Check if any vouchers already exist for these purchase order items with internal suppliers
        $existingVouchers = Voucher::where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('purchase_order_item_id', $internalSupplierProductIds)
            ->exists();

        if ($existingVouchers) {
            throw ValidationException::withMessages([
                'voucher_codes' => 'Vouchers have already been imported for one or more digital products with internal suppliers in this purchase order.',
            ]);
        }
    }
}
