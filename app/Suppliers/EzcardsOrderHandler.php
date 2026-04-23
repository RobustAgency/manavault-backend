<?php

namespace App\Suppliers;

use App\Clients\Ezcards\Orders;
use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Exceptions\SupplierOrderException;
use App\Services\Ezcards\EzcardsVoucherCodeService;
use App\Contracts\Suppliers\PollableSupplierInterface;
use App\Contracts\Suppliers\SupplierOrderHandlerInterface;

class EzcardsOrderHandler implements PollableSupplierInterface, SupplierOrderHandlerInterface
{
    public function __construct(
        private readonly Orders $ordersClient,
        private readonly EzcardsVoucherCodeService $voucherCodeService,
    ) {}

    /**
     * Place an order with Ezcards.
     *
     * The API responds synchronously with a transactionId but voucher codes are
     * delivered asynchronously.
     *
     * @throws SupplierOrderException
     */
    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;

        $items = $purchaseOrderSupplier->purchaseOrderItems()
            ->with('digitalProduct')
            ->get();

        $products = $items->map(fn (PurchaseOrderItem $item) => [
            'sku' => $item->digitalProduct->sku,
            'quantity' => $item->quantity,
        ])->all();

        $payload = [
            'clientOrderNumber' => $purchaseOrder->order_number,
            'enableClientOrderNumberDupCheck' => false,
            'products' => $products,
            'payWithCurrency' => strtoupper($purchaseOrder->currency ?? 'USD'),
        ];

        try {
            $response = $this->ordersClient->placeOrder($payload);
            $transactionId = $response['transactionId'] ?? null;
        } catch (\Throwable $e) {
            $purchaseOrderSupplier->update([
                'status' => PurchaseOrderSupplierStatus::FAILED->value,
            ]);

            Log::error('EzCards placeOrder failed', [
                'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                'error' => $e->getMessage(),
            ]);

            throw new SupplierOrderException(
                "EzCards order failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $purchaseOrderSupplier->update([
            'transaction_id' => $transactionId,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);

        Log::info('EzCards order placed — awaiting voucher retrieval', [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
            'transaction_id' => $transactionId,
        ]);

        return SupplierOrderResult::processing();
    }

    /**
     * Poll EzCards for voucher codes on a previously placed order.
     *
     * Delegates to EzcardsVoucherCodeService which handles idempotent storage,
     * status transitions, and firing NewVouchersAvailable events.
     *
     * @throws SupplierOrderException
     */
    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult
    {
        try {
            $result = $this->voucherCodeService->processPurchaseOrderById(
                $purchaseOrderSupplier->purchaseOrder,
            );
        } catch (\Throwable $e) {
            Log::error('EzCards pollOrder failed', [
                'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                'error' => $e->getMessage(),
            ]);

            throw new SupplierOrderException(
                "EzCards pollOrder failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($result['skipped']) {
            return SupplierOrderResult::processing();
        }

        $purchaseOrderSupplier->refresh();

        $status = $purchaseOrderSupplier->status;
        $statusValue = $status instanceof PurchaseOrderSupplierStatus
            ? $status->value
            : (string) $status;

        if ($statusValue === PurchaseOrderSupplierStatus::COMPLETED->value) {
            return SupplierOrderResult::completed($purchaseOrderSupplier);
        }

        return SupplierOrderResult::processing();
    }
}
