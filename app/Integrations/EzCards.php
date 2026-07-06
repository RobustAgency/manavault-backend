<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use App\Actions\Ezcards\PlaceOrder;
use Illuminate\Support\Facades\Log;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Ezcards\GetVoucherCodes;
use App\Services\Ezcards\SyncDigitalProduct;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;

class EzCards implements SupplierIntegrationContract
{
    public function __construct(
        private readonly PlaceOrder $placeOrderAction,
        private readonly GetVoucherCodes $getVoucherCodesAction,
        private readonly SyncDigitalProduct $syncDigitalProduct,
        private readonly VoucherCipherService $voucherCipherService,
    ) {}

    public function placeOrder(PurchaseOrderItem $purchaseOrderItem): void
    {
        if ($purchaseOrderItem->transaction_id) {
            Log::warning('EzCards placeOrder called for PurchaseOrderItem with existing transaction_id', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
            ]);

            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        $quantity = $purchaseOrderItem->quantity;
        $sku = $purchaseOrderItem->digitalProduct->sku;

        Log::info('EzCards placeOrder: creating order', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'sku' => $sku,
            'quantity' => $quantity,
        ]);

        // TODO: On sandbox try to make two orders with same client order number.
        $response = $this->placeOrderAction->execute([
            'clientOrderNumber' => 'order_item_id_'.$purchaseOrderItem->id,
            'products' => [
                [
                    'sku' => $sku,
                    'quantity' => $quantity,
                ],
            ],
            'payWithCurrency' => $purchaseOrder->currency,
        ]);

        logger()->debug('EzCards order placed.', [
            'order_item_id' => $purchaseOrderItem->id,
            'response' => $response,
        ]);
        $transactionId = $response['data']['transactionId'] ?? null;
        $purchaseOrderItem->update(['transaction_id' => $transactionId, 'status' => PurchaseOrderItemStatus::PROCESSING]);

        Log::info('EzCards placeOrder: order placed', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'transaction_id' => $transactionId,
        ]);
    }

    public function updateOrder(PurchaseOrderItem $purchaseOrderItem): void
    {
        $response = $this->getVoucherCodesAction->execute((int) $purchaseOrderItem->transaction_id);

        /** @var array<int, array<string, mixed>> $responseData */
        $responseData = $response['data'] ?? [];
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $codes */
        $codes = collect($responseData)->flatMap(fn (array $product): array => $product['codes'] ?? []);

        $allCompleted = $codes->isNotEmpty() && $codes->every(
            fn ($code) => ($code['status'] ?? null) === 'COMPLETED'
        );

        if (! $allCompleted) {
            logger()->debug('EzCards order not completed yet.', [
                'order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
                'response' => $response,
            ]);

            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        $isSuccess = $this->storeVoucherCodes($purchaseOrder, $purchaseOrderItem, $response);

        if (! $isSuccess) {
            Log::error('Failed to store vouchers for EzCards order', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
                'response' => $response,
            ]);

            return;
        }

        $purchaseOrderItem->update(['status' => PurchaseOrderItemStatus::FULFILLED]);

        Log::info('EzCards updateOrder: order fulfilled', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'transaction_id' => $purchaseOrderItem->transaction_id,
            'voucher_count' => $codes->count(),
        ]);
    }

    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem, array $voucherCodesResponse): bool
    {
        $productVouchers = $voucherCodesResponse['data'] ?? [];

        $purchaseOrderItem->load('digitalProduct');

        DB::beginTransaction();
        try {
            foreach ($productVouchers as $productData) {
                $codes = $productData['codes'] ?? [];

                foreach ($codes as $voucherData) {
                    $code = $voucherData['redeemCode'];
                    $pinCode = $voucherData['pinCode'] ?? null;
                    $stockId = $voucherData['stockId'] ?? null;

                    $code = $this->voucherCipherService->encryptCode($code);
                    Voucher::create([
                        'code' => $code,
                        'purchase_order_id' => $purchaseOrderItem->purchase_order_id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'status' => 'available',
                        'pin_code' => $pinCode,
                        'stock_id' => $stockId,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProduct->processSyncAllProducts();
    }
}
