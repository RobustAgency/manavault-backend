<?php

namespace App\Services\Voucher;

use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class VoucherPurchaseOrderValidator
{
    public function validate(array $data): PurchaseOrder
    {
        $purchaseOrderId = $data['purchase_order_id'] ?? null;

        if (! $purchaseOrderId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order ID is required.',
            ]);
        }

        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::with('items')->find($purchaseOrderId);

        if (! $purchaseOrder) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order not found.',
            ]);
        }

        $this->validateVoucherProducts($purchaseOrder, $data);
        $this->validateVoucherQuantities($purchaseOrder, $data);

        return $purchaseOrder;
    }

    /**
     * Ensure voucher count per digital product matches the purchase order item quantity.
     * Validates that each digital product and supplier combination has the correct number of vouchers.
     */
    private function validateVoucherQuantities(PurchaseOrder $purchaseOrder, array $data): void
    {
        if (isset($data['voucher_codes']) && is_array($data['voucher_codes'])) {
            // Group voucher codes by digital product
            $codesByProductId = [];
            foreach ($data['voucher_codes'] as $voucher) {
                $productId = $voucher['digitalProductID'] ?? null;
                if ($productId) {
                    $codesByProductId[$productId] = ($codesByProductId[$productId] ?? 0) + 1;
                }
            }

            // Load items with supplier relationships
            $purchaseOrder->load([
                'items' => fn ($query) => $query->with('digitalProduct.supplier'),
            ]);

            // Validate quantity for each digital product with internal supplier
            foreach ($purchaseOrder->items as $item) {
                $supplier = $item->digitalProduct?->supplier;

                // Skip items with non-internal suppliers
                if ($supplier?->type !== 'internal') {
                    continue;
                }

                $productId = $item->digital_product_id;
                $expectedQuantity = $item->quantity;
                $actualQuantity = $codesByProductId[$productId] ?? 0;

                if ($actualQuantity !== $expectedQuantity) {
                    throw ValidationException::withMessages([
                        'voucher_codes' => "Digital product ID {$productId} must have exactly {$expectedQuantity} voucher codes, but {$actualQuantity} provided.",
                    ]);
                }
            }
        }
    }

    /**
     * Ensure voucher products exist in the purchase order.
     */
    private function validateVoucherProducts(PurchaseOrder $purchaseOrder, array $data): void
    {
        if (! isset($data['voucher_codes']) || ! is_array($data['voucher_codes'])) {
            return;
        }

        $itemsByProductId = $purchaseOrder->items
            ->keyBy('digital_product_id');

        foreach ($data['voucher_codes'] as $index => $voucher) {
            $productId = $voucher['digitalProductID'] ?? null;

            if (! $productId || ! $itemsByProductId->has($productId)) {
                throw ValidationException::withMessages([
                    "voucher_codes.{$index}.digitalProductID" => "Digital product ID {$productId} does not exist in the purchase order.",
                ]);
            }
        }
    }
}
