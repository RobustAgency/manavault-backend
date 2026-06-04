<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Gift2Games\CreateOrder;
use App\Actions\Gift2Games\CheckBalance;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2Games implements SupplierIntegrationContract
{
    public function __construct(
        private readonly string $supplierSlug,
        private readonly CreateOrder $createOrderAction,
        private readonly CheckBalance $checkBalanceAction,
        private readonly VoucherCipherService $voucherCipherService,
        private readonly SyncDigitalProducts $syncDigitalProducts,
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        $purchaseOrder = $item->purchaseOrder;

        $remainingCost = $this->calculateRemainingCost($item);

        if ($remainingCost > 0 && ! $this->hasSufficientBalance($remainingCost)) {
            Log::error('Gift2Games: insufficient balance', [
                'supplier_slug' => $this->supplierSlug,
                'purchase_order_item_id' => $item->id,
                'required' => $remainingCost,
            ]);

            PurchaseOrderSupplier::where('supplier_id', $item->supplier_id)
                ->where('purchase_order_id', $item->purchase_order_id)
                ->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);

            $this->purchaseOrderStatusService->updateStatus($purchaseOrder->refresh());

            throw new \Exception("Insufficient balance in Gift2Games wallet [{$this->supplierSlug}] to place the order.");
        }

        $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();
        $newDigitalProductIds = [];

        for ($i = $existingCount; $i < $item->quantity; $i++) {
            try {
                $response = $this->createOrderAction->execute([
                    'productId' => (int) $item->digitalProduct->sku,
                    'referenceNumber' => $purchaseOrder->order_number,
                ], $this->supplierSlug);
            } catch (\Exception $e) {
                Log::error('Gift2Games: create order failed for unit', [
                    'supplier_slug' => $this->supplierSlug,
                    'purchase_order_item_id' => $item->id,
                    'unit' => $i + 1,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $voucherData = $response['data'];
            $code = isset($voucherData['serialCode'])
                ? $this->voucherCipherService->encryptCode($voucherData['serialCode'])
                : null;

            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'code' => $code,
                'serial_number' => $voucherData['serialNumber'] ?? null,
                'status' => 'available',
            ]);

            $newDigitalProductIds[] = $item->digital_product_id;
        }

        $totalCreated = Voucher::where('purchase_order_item_id', $item->id)->count();

        if ($totalCreated < $item->quantity) {
            Log::warning('Gift2Games: partial fulfillment, item left pending for retry', [
                'supplier_slug' => $this->supplierSlug,
                'purchase_order_item_id' => $item->id,
                'expected' => $item->quantity,
                'created' => $totalCreated,
            ]);

            if (! empty($newDigitalProductIds)) {
                event(new NewVouchersAvailable(
                    array_unique($newDigitalProductIds),
                    $purchaseOrder->id,
                    $purchaseOrder->sale_order_id,
                ));
            }

            return;
        }

        $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);

        $allFulfilled = PurchaseOrderItem::where('supplier_id', $item->supplier_id)
            ->where('purchase_order_id', $item->purchase_order_id)
            ->get()
            ->every(fn (PurchaseOrderItem $i) => $i->status === PurchaseOrderItemStatus::FULFILLED);

        if ($allFulfilled) {
            PurchaseOrderSupplier::where('supplier_id', $item->supplier_id)
                ->where('purchase_order_id', $item->purchase_order_id)
                ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        }

        $this->purchaseOrderStatusService->updateStatus($purchaseOrder->refresh());

        if (! empty($newDigitalProductIds)) {
            event(new NewVouchersAvailable(
                array_unique($newDigitalProductIds),
                $purchaseOrder->id,
                $purchaseOrder->sale_order_id,
            ));
        }
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        Log::debug('Gift2Games: updateOrder called but G2G is synchronous, nothing to poll', [
            'purchase_order_item_id' => $item->id,
        ]);
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->syncForSlug($this->supplierSlug);
    }

    private function calculateRemainingCost(PurchaseOrderItem $item): float
    {
        $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();
        $remainingQty = max(0, $item->quantity - $existingCount);
        $unitCost = $item->quantity > 0 ? (float) $item->subtotal / $item->quantity : 0.0;

        return $unitCost * $remainingQty;
    }

    private function hasSufficientBalance(float $requiredAmount): bool
    {
        $response = $this->checkBalanceAction->execute($this->supplierSlug);
        $availableBalance = (float) ($response['data']['userBalance'] ?? 0);

        return $availableBalance >= $requiredAmount;
    }
}
