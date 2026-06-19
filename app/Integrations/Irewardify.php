<?php

namespace App\Integrations;

use App\Models\Voucher;
use Illuminate\Support\Carbon;
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
            $voucher = $this->extractVoucher($deliveryItem);

            if ($voucher['code'] === null) {
                Log::warning('Irewardify updateOrder delivery item missing code', [
                    'purchase_order_item_id' => $item->id,
                    'transaction_id' => $item->transaction_id,
                    'delivery_item_id' => $deliveryItem['Id'] ?? $deliveryItem['id'] ?? null,
                ]);

                return;
            }

            $vouchers[] = $voucher;
        }

        $purchaseOrder = $item->purchaseOrder;

        foreach ($vouchers as $voucher) {
            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $item->id,
                'code' => $this->voucherCipherService->encryptCode($voucher['code']),
                'serial_number' => null,
                'pin_code' => $voucher['pin'] !== null ? (string) $voucher['pin'] : null,
                'expires_at' => $voucher['expires_at'],
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);
    }

    /**
     * Normalize a delivery item into a voucher payload.
     *
     * Irewardify returns two delivery shapes:
     *  - cardCode/pin/expirationDate keys directly on the item
     *  - a Codes array of Label/Value pairs (Code, PIN)
     *
     * @param  array<string, mixed>  $deliveryItem
     * @return array{code: ?string, pin: ?string, expires_at: ?\Illuminate\Support\Carbon}
     */
    private function extractVoucher(array $deliveryItem): array
    {
        if (isset($deliveryItem['cardCode'])) {
            return [
                'code' => $deliveryItem['cardCode'],
                'pin' => $deliveryItem['pin'] ?? null,
                'expires_at' => isset($deliveryItem['expirationDate'])
                    ? Carbon::parse($deliveryItem['expirationDate'])
                    : null,
            ];
        }

        $codes = collect((array) ($deliveryItem['Codes'] ?? []))->pluck('Value', 'Label');

        return [
            'code' => $codes->get('Code'),
            'pin' => $codes->get('PIN'),
            'expires_at' => null,
        ];
    }

    public function syncProducts(): void
    {
        $this->syncProducts->processSyncAllProducts();
    }
}
