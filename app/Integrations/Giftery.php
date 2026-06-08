<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Services\Giftery\SyncProducts;
use App\Actions\Giftery\PlaceOrderAction;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;
use App\Clients\Giftery\Client as GifteryClient;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Giftery implements SupplierIntegrationContract
{
    public function __construct(
        private readonly PlaceOrderAction $placeOrderAction,
        private readonly GifteryClient $gifteryClient,
        private readonly SyncProducts $syncProducts,
        private readonly VoucherCipherService $voucherCipherService,
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        if ($item->transaction_id) {
            Log::warning('Giftery placeOrder skipped: transaction_id already set', [
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
            ]);

            return;
        }

        $payload = [
            'itemId' => (int) $item->digitalProduct->sku,
            'fields' => [['key' => 'quantity', 'value' => $item->quantity]],
            'clientTime' => now()->toIso8601String(),
            'referenceId' => 'order_item_id_'.$item->id,
        ];

        $response = $this->placeOrderAction->execute($payload);

        $transactionUUID = $response['transactionUUID'];

        $item->update(['transaction_id' => $transactionUUID, 'status' => PurchaseOrderItemStatus::PROCESSING]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $operation = $this->gifteryClient->getOperation($item->transaction_id);

        $vouchers = $operation['vouchers'] ?? [];

        if (empty($vouchers)) {
            return;
        }

        $purchaseOrder = $item->purchaseOrder;

        foreach ($vouchers as $voucherData) {
            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'code' => $this->voucherCipherService->encryptCode($voucherData['pin'] ?? null),
                'serial_number' => $voucherData['serialNumber'] ?? null,
                'pin_code' => null,
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
        $this->syncProducts->processSyncAllProducts();
    }
}
