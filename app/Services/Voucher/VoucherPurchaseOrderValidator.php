<?php

namespace App\Services\Voucher;

use App\DTOs\VoucherDTO;
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

        $this->validateVoucherProductsDTO($purchaseOrder, $voucherDTOs);
        $this->validateVoucherQuantitiesDTO($purchaseOrder, $voucherDTOs);

        return $purchaseOrder;
    }

    /**
     * Validate that all voucher digital products exist in the purchase order (DTO version)
     * AND that all purchase order items have corresponding vouchers provided
     *
     * @param  array<int, VoucherDTO>  $voucherDTOs
     */
    private function validateVoucherProductsDTO(PurchaseOrder $purchaseOrder, array $voucherDTOs): void
    {
        $purchaseProductIds = $purchaseOrder->items
            ->pluck('digital_product_id')
            ->toArray();

        $voucherProductIds = collect($voucherDTOs)
            ->pluck('digital_product_id')
            ->toArray();

        $invalidVoucherIds = array_diff($voucherProductIds, $purchaseProductIds);

        if (! empty($invalidVoucherIds)) {
            throw ValidationException::withMessages([
                'voucher_codes' => 'Some voucher digital products do not exist in the purchase order.',
            ]);
        }

        // Validate: all purchase order products have vouchers
        $missingVoucherIds = array_diff($purchaseProductIds, $voucherProductIds);

        if (! empty($missingVoucherIds)) {
            throw ValidationException::withMessages([
                'voucher_codes' => 'All digital products in the purchase order must have voucher codes.',
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

        foreach ($purchaseOrder->items as $item) {
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
}
