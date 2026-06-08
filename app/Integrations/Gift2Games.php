<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\Gift2GamesOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Enums\Gift2GamesOrderStatus;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Gift2Games\CreateOrder;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2Games implements SupplierIntegrationContract
{
    public function __construct(
        private readonly string $supplierSlug,
        private readonly CreateOrder $createOrder,
        private readonly SyncDigitalProducts $syncDigitalProducts,
        private readonly VoucherCipherService $voucherCipherService,
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        $batchNumber = 'batch_'.$item->id;

        for ($i = 0; $i < $item->quantity; $i++) {
            Gift2GamesOrder::create([
                'batch_number' => $batchNumber,
                'transaction_id' => null,
                'status' => Gift2GamesOrderStatus::PROCESSING,
            ]);
        }

        $item->update(['transaction_id' => $batchNumber, 'status' => PurchaseOrderItemStatus::PROCESSING]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $batchNumber = $item->transaction_id;
        $purchaseOrder = $item->purchaseOrder;

        $pendingOrders = Gift2GamesOrder::where('batch_number', $batchNumber)
            ->where('status', '!=', Gift2GamesOrderStatus::FULFILLED)
            ->get();

        $orderData = [
            'productId' => (int) $item->digitalProduct->sku,
            'referenceNumber' => 'order_item_id_'.$item->id,
        ];

        foreach ($pendingOrders->chunk(5) as $chunk) {
            $responses = $this->createOrder->execute($orderData, $this->supplierSlug, $chunk->count());

            foreach ($chunk->values() as $index => $order) {
                $response = $responses[$index];

                if ($response === null) {
                    Log::error('Gift2Games updateOrder error', [
                        'supplier_slug' => $this->supplierSlug,
                        'product_item_id' => $item->id,
                        'sku' => $item->digitalProduct->sku ?? null,
                    ]);

                    continue;
                }

                $voucherData = $response['data'];
                $voucherCode = isset($voucherData['serialCode'])
                    ? $this->voucherCipherService->encryptCode($voucherData['serialCode'])
                    : null;

                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $item->id,
                    'code' => $voucherCode,
                    'serial_number' => $voucherData['serialNumber'] ?? null,
                    'status' => 'available',
                ]);

                $order->update([
                    'transaction_id' => $voucherData['orderId'],
                    'status' => Gift2GamesOrderStatus::FULFILLED,
                ]);
            }
        }

        $hasPending = Gift2GamesOrder::where('batch_number', $batchNumber)
            ->where('status', '!=', Gift2GamesOrderStatus::FULFILLED)
            ->exists();

        if (! $hasPending) {
            $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);
            $allCompleted = PurchaseOrderItem::where('supplier_id', $item->supplier_id)
                ->where('purchase_order_id', $item->purchase_order_id)
                ->get()
                ->every(fn (PurchaseOrderItem $item) => $item->status === PurchaseOrderItemStatus::FULFILLED);

            if ($allCompleted) {
                PurchaseOrderSupplier::where('supplier_id', $item->supplier_id)
                    ->where('purchase_order_id', $item->purchase_order_id)
                    ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            }

            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
        }
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->syncForSlug($this->supplierSlug);
    }
}
