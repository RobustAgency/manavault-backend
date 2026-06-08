<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Tikkery\GetOrder;
use App\Actions\Tikkery\CreateOrder;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Tikkery\SyncDigitalProducts;
use App\Services\Voucher\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Tikkery implements SupplierIntegrationContract
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly GetOrder $getOrder,
        private readonly SyncDigitalProducts $syncDigitalProducts,
        private readonly VoucherCipherService $voucherCipherService,
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        if ($item->transaction_id) {
            Log::warning('Tikkery placeOrder skipped: transaction_id already set', [
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
            ]);

            return;
        }

        $response = $this->createOrder->execute([
            'lineItems' => [[
                'sku' => $item->digitalProduct->sku,
                'qty' => (string) $item->quantity,
                'price' => (float) $item->unit_cost,
            ]],
            'customerReference' => 'order_item_id_'.$item->id,
        ]);

        $orderNumber = $response['order']['number'] ?? null;

        $item->update(['transaction_id' => $orderNumber, 'status' => PurchaseOrderItemStatus::PROCESSING]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $response = $this->getOrder->execute($item->transaction_id);

        $isCompleted = (bool) ($response['order']['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        if (! $isCompleted || empty($codes)) {
            return;
        }

        $purchaseOrder = $item->purchaseOrder;

        foreach ($codes as $code) {
            $redemptionCode = $code['redemptionCode'] ?? null;

            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'code' => $redemptionCode ? $this->voucherCipherService->encryptCode($redemptionCode) : null,
                'serial_number' => $code['serial'] ?? null,
                'pin_code' => $code['pin'] ?? null,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);

        $allCompleted = PurchaseOrderItem::where('supplier_id', $item->supplier_id)
            ->where('purchase_order_id', $item->purchase_order_id)
            ->get()
            ->every(fn (PurchaseOrderItem $i) => $i->status === PurchaseOrderItemStatus::FULFILLED);

        if ($allCompleted) {
            PurchaseOrderSupplier::where('supplier_id', $item->supplier_id)
                ->where('purchase_order_id', $item->purchase_order_id)
                ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        }

        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->processSyncAllProducts();
    }
}
