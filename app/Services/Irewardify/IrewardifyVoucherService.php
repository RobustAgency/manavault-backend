<?php

namespace App\Services\Irewardify;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Actions\Irewardify\GetOrderDelivery;
use App\Services\Voucher\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class IrewardifyVoucherService
{
    public function __construct(
        private GetOrderDelivery $getOrderDelivery,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private VoucherCipherService $voucherCipherService,
    ) {}

    /**
     * Process all pending Irewardify purchase orders and fetch their voucher codes.
     *
     * @return array<string, mixed> Summary of processing results
     */
    public function processAllPurchaseOrders(): array
    {
        $summary = [
            'total_orders' => 0,
            'processed_orders' => 0,
            'skipped_orders' => 0,
            'failed_orders' => 0,
            'total_vouchers_added' => 0,
            'errors' => [],
        ];

        $purchaseOrders = $this->getUnprocessedPurchaseOrders();
        $summary['total_orders'] = $purchaseOrders->count();

        foreach ($purchaseOrders as $purchaseOrder) {
            try {
                $result = $this->processPurchaseOrder($purchaseOrder);

                if ($result['skipped']) {
                    $summary['skipped_orders']++;
                } else {
                    $summary['processed_orders']++;
                    $summary['total_vouchers_added'] += $result['vouchers_added'];
                }
            } catch (\Exception $e) {
                $summary['failed_orders']++;
                $summary['errors'][] = [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ];

                Log::error('Irewardify: failed to process vouchers for purchase order', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Process a single purchase order by model instance.
     *
     * @return array<string, mixed>
     */
    public function processPurchaseOrderById(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->load(['vouchers', 'items.digitalProduct']);

        return $this->processPurchaseOrder($purchaseOrder);
    }

    /**
     * Get all unprocessed Irewardify purchase orders (PROCESSING status with a transaction_id).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    private function getUnprocessedPurchaseOrders()
    {
        $purchaseOrderIds = PurchaseOrderSupplier::whereHas('supplier', function ($query) {
            $query->where('slug', 'irewardify');
        })
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNotNull('transaction_id')
            ->pluck('purchase_order_id');

        return PurchaseOrder::with(['vouchers', 'items.digitalProduct'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();
    }

    /**
     * Process a single purchase order — fetch delivery codes and persist vouchers.
     *
     * @return array<string, mixed>
     */
    private function processPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $result = [
            'skipped' => false,
            'vouchers_added' => 0,
            'reason' => null,
        ];

        $purchaseOrderSupplier = PurchaseOrderSupplier::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('supplier', function ($query) {
                $query->where('slug', 'irewardify');
            })
            ->first();

        if (! $purchaseOrderSupplier || ! $purchaseOrderSupplier->transaction_id) {
            $result['skipped'] = true;
            $result['reason'] = 'No Irewardify transaction ID (orderId) found';

            return $result;
        }

        $orderId = $purchaseOrderSupplier->transaction_id;

        $deliveryResponse = $this->getOrderDelivery->execute($orderId);

        $vouchersAdded = $this->storeVoucherCodes($purchaseOrder, $deliveryResponse);
        $result['vouchers_added'] = $vouchersAdded;

        $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);

        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

        return $result;
    }

    /**
     * Persist voucher codes from the Irewardify delivery response.
     *
     * @return int Number of vouchers added
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $deliveryResponse): int
    {
        $vouchersAdded = 0;
        $deliveryItems = $deliveryResponse['data'] ?? [];

        if (empty($deliveryItems)) {
            Log::warning('Irewardify: empty delivery data for purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'order_id' => $deliveryResponse['orderId'] ?? null,
            ]);

            return 0;
        }

        $purchaseOrder->load('items.digitalProduct');

        DB::beginTransaction();

        try {
            foreach ($deliveryItems as $deliveryItem) {
                $cardCode = $deliveryItem['cardCode'] ?? null;
                $pin = (string) ($deliveryItem['pin'] ?? '');
                $stockId = (string) ($deliveryItem['id'] ?? '');
                $brand = $deliveryItem['Brand'] ?? null;
                $denom = $deliveryItem['Denom'] ?? null;
                $expirationDate = isset($deliveryItem['expirationDate'])
                    ? \Carbon\Carbon::parse($deliveryItem['expirationDate'])
                    : null;

                if (! $cardCode) {
                    Log::warning('Irewardify: skipping delivery item with no cardCode', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'item' => $deliveryItem,
                    ]);

                    continue;
                }

                // Match delivery item to a purchase order item via the variant's item_id stored in metadata
                $purchaseOrderItem = $purchaseOrder->items->first(function ($item) use ($deliveryItem) {
                    $variantItemId = $item->digitalProduct->metadata['variant']['item_id'] ?? null;

                    return $variantItemId === (string) ($deliveryItem['item_id'] ?? '')
                        || ($item->digitalProduct->brand === ($deliveryItem['Brand'] ?? null)
                            && (float) $item->digitalProduct->face_value === (float) ltrim($deliveryItem['Denom'] ?? '0', '$'));
                });

                if (! $purchaseOrderItem) {
                    Log::warning('Irewardify: no matching purchase order item for delivery item', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'brand' => $brand,
                        'denom' => $denom,
                        'stock_id' => $stockId,
                    ]);

                    continue;
                }

                // Skip duplicates by stock_id
                $exists = Voucher::where('purchase_order_id', $purchaseOrder->id)
                    ->where('stock_id', $stockId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $encryptedCode = $this->voucherCipherService->encryptCode($cardCode);

                Voucher::create([
                    'code' => $encryptedCode,
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'status' => 'available',
                    'pin_code' => $pin ?: null,
                    'stock_id' => $stockId,
                    'serial_number' => null,
                    'expires_at' => $expirationDate,
                ]);

                $vouchersAdded++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if ($vouchersAdded > 0) {
            $digitalProductIds = $purchaseOrder->items->pluck('digital_product_id')->unique()->values()->all();
            event(new NewVouchersAvailable($digitalProductIds));
        }

        return $vouchersAdded;
    }
}
