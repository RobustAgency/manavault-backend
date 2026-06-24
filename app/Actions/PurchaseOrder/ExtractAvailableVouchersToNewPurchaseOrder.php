<?php

namespace App\Actions\PurchaseOrder;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Enums\VoucherCodeStatus;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Repositories\PurchaseOrderRepository;

class ExtractAvailableVouchersToNewPurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderRepository $purchaseOrderRepository,
    ) {}

    /**
     * Move the AVAILABLE (un-allocated) vouchers of the given purchase order onto a
     * fresh general-stock purchase order (sale_order_id = null), replicating the
     * matching line items and repointing each voucher's purchase_order_item_id.
     *
     * The source purchase order is left untouched. The new order/items/supplier use
     * completed statuses so nothing is re-ordered from an external supplier.
     *
     * @return array<string, mixed>
     */
    public function execute(int $sourcePurchaseOrderId, bool $dryRun = false): array
    {
        $sourcePurchaseOrder = PurchaseOrder::find($sourcePurchaseOrderId);

        if (! $sourcePurchaseOrder) {
            throw new \RuntimeException("Purchase order not found with ID: {$sourcePurchaseOrderId}");
        }

        logger()->info('ExtractAvailableVouchersToNewPurchaseOrder: started', [
            'source_purchase_order_id' => $sourcePurchaseOrderId,
            'dry_run' => $dryRun,
        ]);

        DB::beginTransaction();

        try {
            $vouchers = Voucher::query()
                ->where('purchase_order_id', $sourcePurchaseOrder->id)
                ->where('status', VoucherCodeStatus::AVAILABLE->value)
                ->with('purchaseOrderItem')
                ->lockForUpdate()
                ->get();

            // Vouchers without a usable line item can't be replicated faithfully —
            // leave them on the source order and report them as skipped.
            $movable = $vouchers->filter(fn (Voucher $voucher) => $voucher->purchaseOrderItem !== null);
            $skipped = $vouchers->count() - $movable->count();

            if ($movable->isEmpty()) {
                DB::rollBack();

                logger()->info('ExtractAvailableVouchersToNewPurchaseOrder: nothing to move', [
                    'source_purchase_order_id' => $sourcePurchaseOrderId,
                    'skipped' => $skipped,
                ]);

                return $this->summary($sourcePurchaseOrderId, null, null, 0, 0, $skipped, []);
            }

            $newPurchaseOrder = $this->purchaseOrderRepository->createPurchaseOrder([
                'total_price' => 0,
                'order_number' => $this->generateOrderNumber(),
                'status' => PurchaseOrderStatus::COMPLETED->value,
                'currency' => $sourcePurchaseOrder->currency,
                'sale_order_id' => null,
            ]);

            $totalPrice = 0.0;
            $supplierIds = [];
            $breakdown = [];

            // One new line item per source item, so the new order mirrors one product each.
            foreach ($movable->groupBy('purchase_order_item_id') as $group) {
                /** @var \App\Models\PurchaseOrderItem $sourceItem */
                $sourceItem = $group->first()->purchaseOrderItem;
                $quantity = $group->count();
                $unitCost = (float) $sourceItem->unit_cost;
                $subtotal = $unitCost * $quantity;
                $totalPrice += $subtotal;

                $newItem = $this->purchaseOrderRepository->createPurchaseOrderItem([
                    'purchase_order_id' => $newPurchaseOrder->id,
                    'supplier_id' => $sourceItem->supplier_id,
                    'digital_product_id' => $sourceItem->digital_product_id,
                    'digital_product_name' => $sourceItem->digital_product_name,
                    'digital_product_sku' => $sourceItem->digital_product_sku,
                    'digital_product_brand' => $sourceItem->digital_product_brand,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                    'status' => PurchaseOrderItemStatus::FULFILLED->value,
                ]);

                Voucher::whereIn('id', $group->pluck('id'))->update([
                    'purchase_order_id' => $newPurchaseOrder->id,
                    'purchase_order_item_id' => $newItem->id,
                ]);

                if ($sourceItem->supplier_id !== null) {
                    $supplierIds[$sourceItem->supplier_id] = true;
                }

                $breakdown[] = [
                    'digital_product_id' => $sourceItem->digital_product_id,
                    'digital_product_name' => $sourceItem->digital_product_name,
                    'quantity' => $quantity,
                ];
            }

            // Mirror supplier linkage as COMPLETED. PlacePendingPurchaseOrdersCommand only
            // targets PROCESSING suppliers with a null transaction_id, so this is never re-placed.
            foreach (array_keys($supplierIds) as $supplierId) {
                $this->purchaseOrderRepository->createPurchaseOrderSupplier([
                    'purchase_order_id' => $newPurchaseOrder->id,
                    'supplier_id' => $supplierId,
                    'status' => PurchaseOrderSupplierStatus::COMPLETED->value,
                ]);
            }

            $newPurchaseOrder->update(['total_price' => $totalPrice]);

            $movedCount = $movable->count();

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            logger()->info('ExtractAvailableVouchersToNewPurchaseOrder: finished', [
                'source_purchase_order_id' => $sourcePurchaseOrderId,
                'new_purchase_order_id' => $newPurchaseOrder->id,
                'vouchers_moved' => $movedCount,
                'items_created' => count($breakdown),
                'skipped' => $skipped,
                'dry_run' => $dryRun,
            ]);

            return $this->summary(
                $sourcePurchaseOrderId,
                $dryRun ? null : $newPurchaseOrder->id,
                $dryRun ? null : $newPurchaseOrder->order_number,
                $movedCount,
                count($breakdown),
                $skipped,
                $breakdown,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            logger()->error('ExtractAvailableVouchersToNewPurchaseOrder: failed', [
                'source_purchase_order_id' => $sourcePurchaseOrderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function summary(
        int $sourcePurchaseOrderId,
        ?int $newPurchaseOrderId,
        ?string $newOrderNumber,
        int $vouchersMoved,
        int $itemsCreated,
        int $skipped,
        array $breakdown,
    ): array {
        return [
            'source_purchase_order_id' => $sourcePurchaseOrderId,
            'new_purchase_order_id' => $newPurchaseOrderId,
            'new_order_number' => $newOrderNumber,
            'vouchers_moved' => $vouchersMoved,
            'items_created' => $itemsCreated,
            'skipped' => $skipped,
            'breakdown' => $breakdown,
        ];
    }

    private function generateOrderNumber(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(uniqid());
    }
}
