<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Enums\VoucherCodeStatus;
use App\Actions\Tikkery\GetOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Actions\Tikkery\CreateOrder;
use App\Enums\PurchaseOrderItemStatus;
use App\Services\Tikkery\SyncDigitalProducts;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;

class Tikkery implements SupplierIntegrationContract
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly GetOrder $getOrder,
        private readonly SyncDigitalProducts $syncDigitalProducts,
        private readonly VoucherCipherService $voucherCipherService,
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

        Log::info('Tikkery placeOrder: creating order', [
            'purchase_order_item_id' => $item->id,
            'sku' => $item->digitalProduct->sku,
            'quantity' => $item->quantity,
        ]);

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

        Log::info('Tikkery placeOrder: order placed', [
            'purchase_order_item_id' => $item->id,
            'transaction_id' => $orderNumber,
        ]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $response = $this->getOrder->execute($item->transaction_id);

        $isCompleted = (bool) ($response['order']['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        if (! $isCompleted || empty($codes)) {
            return;
        }

        if (count($codes) !== $item->quantity) {
            Log::warning('Tikkery updateOrder code count does not match item quantity', [
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
                'code_count' => count($codes),
                'quantity' => $item->quantity,
            ]);

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

        Log::info('Tikkery updateOrder: order fulfilled', [
            'purchase_order_item_id' => $item->id,
            'transaction_id' => $item->transaction_id,
            'code_count' => count($codes),
        ]);
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->processSyncAllProducts();
    }
}
