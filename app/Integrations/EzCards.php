<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use App\Actions\Ezcards\PlaceOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Ezcards\GetVoucherCodes;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Ezcards\SyncDigitalProduct;
use App\Contracts\SupplierIntegrationContract;

class EzCards implements SupplierIntegrationContract
{
    public function __construct(
        private readonly PlaceOrder $placeOrderAction,
        private readonly GetVoucherCodes $getVoucherCodesAction,
        private readonly SyncDigitalProduct $syncDigitalProduct,
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
        $transactionId = $response['transactionId'] ?? ($response['id'] ?? null);
        $purchaseOrderItem->update(['transaction_id' => $transactionId]);

        // $products = [];

        // foreach ($orderItems as $item) {
        //     $products[] = [
        //         'sku' => $item->digitalProduct->sku,
        //         'quantity' => $item->quantity,
        //     ];
        // }

        // $response = $this->placeOrderAction->execute([
        //     'clientOrderNumber' => $orderNumber,
        //     'products' => $products,
        //     'payWithCurrency' => strtoupper($currency),
        // ]);

        // Log::info('EzCards order placed', [
        //     'order_number' => $orderNumber,
        //     'response' => $response,
        // ]);

        // return $response['data'] ?? [];
    }

    public function updateOrder(PurchaseOrderItem $purchaseOrderItem): array
    {
        // Once we realise vouchers for all purcahase order items, we'll then update the status of purchase order supplier.
        // We will do this based on purchase order item status.
        $response = $this->getVoucherCodesAction->execute((int) $purchaseOrderItem->transaction_id);
        $isSuccess = $this->storeVoucherCodes($purchaseOrderItem->purchaseOrder, $response);

        if (! $isSuccess) {
            Log::error('Failed to store vouchers for EzCards order', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'transaction_id' => $purchaseOrderItem->transaction_id,
                'response' => $response,
            ]);

            return [];
        }

        $purchaseOrderItem->update(['status' => PurchaseOrderItemStatus::COMPLETED]);

        // for legacy purposes.
        $otherPurchaseOrderItems = PurchaseOrderItem::where('supplier_id', $purchaseOrderItem->supplier_id)
            ->where('purchase_order_id', $purchaseOrderItem->purchase_order_id);

        $otherPurchaseOrderItems->every(function (PurchaseOrderItem $item) {
            return $item->status === PurchaseOrderItemStatus::COMPLETED;
        });
        if ($otherPurchaseOrderItems) {
            PurchaseOrderSupplier::where('supplier_id', $purchaseOrderItem->supplier_id)
                ->where('purchase_order_id', $purchaseOrderItem->purchase_order_id)
                ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        }

        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

        return $response['data'] ?? [];
    }

    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): int
    {
        $vouchersAdded = 0;

        // Handle response structure - the data array contains items for each product
        $productVouchers = $voucherCodesResponse['data'] ?? [];

        // Load purchase order items with digital products to match vouchers to items
        $purchaseOrder->load('items.digitalProduct');

        DB::beginTransaction();
        try {
            // Process each product's vouchers from the API response
            foreach ($productVouchers as $productData) {
                $sku = $productData['sku'];
                $codes = $productData['codes'];

                // Find the corresponding purchase order item by matching the SKU
                $purchaseOrderItem = $purchaseOrder->items->first(function ($item) use ($sku) {
                    return $item->digitalProduct->sku === $sku;
                });

                if (! $purchaseOrderItem) {
                    Log::warning('No purchase order item found for EzCards voucher', [
                        'sku' => $sku,
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);

                    continue;
                }

                // Process each voucher code for this product
                foreach ($codes as $voucherData) {
                    $code = $voucherData['redeemCode'] ?? null;
                    $pinCode = $voucherData['pinCode'] ?? null;
                    $stockId = $voucherData['stockId'] ?? null;
                    $apiStatus = $voucherData['status'];

                    // Determine voucher status based on API status and code availability
                    if ($apiStatus === 'COMPLETED' && $code) {
                        $status = 'available';
                    } elseif ($apiStatus === 'PROCESSING' || ! $code) {
                        $status = 'processing';
                    } else {
                        $status = 'available';
                    }

                    // For processing status without code, create placeholder with stockId
                    if (! $code && $status === 'processing' && $stockId) {
                        // Check if a voucher with this stockId already exists
                        $exists = Voucher::where('purchase_order_id', $purchaseOrder->id)
                            ->where('stock_id', $stockId)
                            ->exists();

                        if (! $exists) {
                            Voucher::create([
                                'code' => null,
                                'purchase_order_id' => $purchaseOrder->id,
                                'purchase_order_item_id' => $purchaseOrderItem->id,
                                'status' => $status,
                                'serial_number' => null,
                                'pin_code' => null,
                                'stock_id' => $stockId,
                            ]);
                            $vouchersAdded++;
                        }

                        continue;
                    }

                    // Skip if no code and not processing
                    if (! $code) {
                        continue;
                    }

                    // Check if voucher code already exists (by code or by stockId)
                    $existingVoucher = Voucher::where('purchase_order_id', $purchaseOrder->id)
                        ->where(function ($query) use ($code, $stockId) {
                            $query->where('code', $code);
                            if ($stockId) {
                                $query->orWhere('stock_id', $stockId);
                            }
                        })
                        ->first();

                    $code = $this->voucherCipherService->encryptCode($code);

                    if (! $existingVoucher) {
                        Voucher::create([
                            'code' => $code,
                            'purchase_order_id' => $purchaseOrder->id,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                        ]);
                        $vouchersAdded++;
                    } else {
                        // Update existing voucher if status or code changed
                        $existingVoucher->update([
                            'code' => $code,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Notify listeners that new vouchers are available so pending sale orders can be fulfilled
        if ($vouchersAdded > 0) {
            $digitalProductIds = $purchaseOrder->items->pluck('digital_product_id')->unique()->values()->all();
            event(new NewVouchersAvailable($digitalProductIds, $purchaseOrder->id, $purchaseOrder->sale_order_id));
        }

        return $vouchersAdded;
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProduct->processSyncAllProducts();
    }
}
