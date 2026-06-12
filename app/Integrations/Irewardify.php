<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Actions\Irewardify\Checkout;
use App\Enums\PurchaseOrderItemStatus;
use App\Services\Irewardify\SyncProducts;
use App\Actions\Irewardify\GetOrderDelivery;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;

class Irewardify implements SupplierIntegrationContract
{
    public function __construct(
        private readonly Checkout $checkout,
        private readonly GetOrderDelivery $getOrderDelivery,
        private readonly SyncProducts $syncProducts,
        private readonly VoucherCipherService $voucherCipherService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        if ($item->transaction_id) {
            Log::warning('Irewardify placeOrder skipped: transaction_id already set', [
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
            ]);

            return;
        }

        $variant = $item->digitalProduct->metadata['variant'] ?? [];

        $itemId = $variant['item_id'] ?? null;
        $productId = $variant['product_id'] ?? null;

        if (! $itemId || ! $productId) {
            throw new \RuntimeException("Irewardify placeOrder: missing item_id or product_id in metadata for digital product SKU: {$item->digital_product_sku}.");
        }

        $payload = [
            'externalOrderId' => 'order_item_id_'.$item->id,
            'items' => [[
                'item_id' => $itemId,
                'productType' => 'Digital',
                'product_id' => $productId,
                'quantity' => $item->quantity,
            ]],
        ];

        $response = $this->checkout->execute($payload);

        $orderId = $response['data']['orderId'] ?? null;

        $item->update(['transaction_id' => $orderId, 'status' => PurchaseOrderItemStatus::PROCESSING]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $response = $this->getOrderDelivery->execute($item->transaction_id);
        Log::info('Irewardify updateOrder response', [
            'purchase_order_item_id' => $item->id,
            'transaction_id' => $item->transaction_id,
            'response' => $response,
        ]);

        $deliveryItems = $response['data'] ?? [];

        if (empty($deliveryItems)) {
            return;
        }

        if (count($deliveryItems) !== $item->quantity) {
            Log::warning('Irewardify updateOrder delivery item count does not match item quantity', [
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
                'delivered_count' => count($deliveryItems),
                'quantity' => $item->quantity,
            ]);

            return;
        }

        $vouchers = [];

        foreach ($deliveryItems as $deliveryItem) {
            $codes = collect($deliveryItem['Codes'] ?? [])->pluck('Value', 'Label');

            $cardCode = $codes->get('Code');

            if (! $cardCode) {
                Log::warning('Irewardify updateOrder delivery item missing code', [
                    'purchase_order_item_id' => $item->id,
                    'transaction_id' => $item->transaction_id,
                    'delivery_item_id' => $deliveryItem['Id'] ?? null,
                ]);

                return;
            }

            $vouchers[] = [
                'code' => $cardCode,
                'pin' => $codes->get('PIN'),
            ];
        }

        $purchaseOrder = $item->purchaseOrder;

        foreach ($vouchers as $voucher) {
            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'code' => $this->voucherCipherService->encryptCode($voucher['code']),
                'serial_number' => null,
                'pin_code' => $voucher['pin'] !== null ? (string) $voucher['pin'] : null,
                'expires_at' => null,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);
    }

    public function syncProducts(): void
    {
        $this->syncProducts->processSyncAllProducts();
    }
}
