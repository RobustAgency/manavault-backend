<?php

namespace App\Services\Ezcards;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Models\PurchaseOrderSupplier;
use App\Actions\Ezcards\GetVoucherCodes;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Voucher\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class EzcardsVoucherCodeService
{
    public function __construct(
        private GetVoucherCodes $getVoucherCodes,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private VoucherCipherService $voucherCipherService
    ) {}

    public function processAllPurchaseOrders(): bool
    {
        $purchaseOrders = $this->getUnprocessedPurchaseOrders();
        Log::info('Found '.count($purchaseOrders).' EZ Cards purchase orders to process.');

        foreach ($purchaseOrders as $purchaseOrder) {
            try {
                $this->processPurchaseOrder($purchaseOrder);
            } catch (\Exception $e) {
                Log::error('Failed to process voucher codes for purchase order', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function getUnprocessedPurchaseOrders()
    {
        $purchaseOrderIds = PurchaseOrderSupplier::whereHas('supplier', function ($query) {
            $query->where('slug', 'ez_cards');
        })->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNotNull('transaction_id')
            ->pluck('purchase_order_id');

        return PurchaseOrder::with(['vouchers', 'items.digitalProduct'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();
    }

    public function processPurchaseOrderById(PurchaseOrder $purchaseOrder): bool
    {
        $purchaseOrder->load(['vouchers', 'items.digitalProduct']);

        return $this->processPurchaseOrder($purchaseOrder);
    }

    public function processPurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {
        $purchaseOrderSupplier = PurchaseOrderSupplier::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('supplier', function ($query) {
                $query->where('slug', 'ez_cards');
            })
            ->first();

        if (! $purchaseOrderSupplier || ! $purchaseOrderSupplier->transaction_id) {
            Log::warning('EZ Cards supplier record missing or has no transaction ID for purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
            ]);

            return false;
        }

        // Ensure relations are loaded before passing to storeVoucherCodes
        $purchaseOrder->loadMissing('items.digitalProduct');

        $transactionID = (int) $purchaseOrderSupplier->transaction_id;
        $voucherCodesResponse = $this->getVoucherCodes->execute($transactionID);
        $result = $this->storeVoucherCodes($purchaseOrder, $voucherCodesResponse);

        if (empty($result['item_ids'])) {
            Log::error('EZ Cards returned no recognisable SKUs for purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
            ]);
        }

        // Scope to only the items ez_cards processed — other suppliers' vouchers are irrelevant here
        $stillProcessing = ! empty($result['item_ids']) && Voucher::where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('purchase_order_item_id', $result['item_ids'])
            ->where('status', 'processing')
            ->exists();

        if (! $stillProcessing) {
            $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
        }

        if ($result['added'] > 0 || $result['updated'] > 0) {
            $digitalProductIds = $purchaseOrder->items->pluck('digital_product_id')->unique()->values()->all();
            event(new NewVouchersAvailable($digitalProductIds));
        }

        return true;
    }

    /**
     * @return array{added: int, updated: int, item_ids: int[]}
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): array
    {
        $productVouchers = $voucherCodesResponse['data'] ?? [];

        // O(1) SKU lookup instead of O(n) collection search per product
        $itemsBySku = $purchaseOrder->items->keyBy(fn ($item) => $item->digitalProduct->sku);

        // Pre-fetch all existing vouchers for this order in one query to avoid N+1
        $allStockIds = collect($productVouchers)
            ->flatMap(fn ($p) => collect($p['codes'])->pluck('stockId'))
            ->filter()
            ->unique()
            ->values();

        $existingVouchers = $allStockIds->isNotEmpty()
            ? Voucher::where('purchase_order_id', $purchaseOrder->id)
                ->whereIn('stock_id', $allStockIds)
                ->get()
                ->keyBy('stock_id')
            : collect();

        $vouchersAdded = 0;
        $vouchersUpdated = 0;
        $processedItemIds = [];
        $toInsert = [];
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($productVouchers as $productData) {
                $sku = $productData['sku'];
                $codes = $productData['codes'];

                $purchaseOrderItem = $itemsBySku->get($sku);

                if (! $purchaseOrderItem) {
                    Log::error('EZ Cards returned SKU not found in purchase order', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'sku' => $sku,
                    ]);
                    continue;
                }

                $processedItemIds[] = $purchaseOrderItem->id;

                $receivedCount = count($codes);
                if ($receivedCount !== $purchaseOrderItem->quantity) {
                    Log::warning('EZ Cards voucher code count mismatch', [
                        'purchase_order_id' => $purchaseOrder->id,
                        'sku' => $sku,
                        'expected' => $purchaseOrderItem->quantity,
                        'received' => $receivedCount,
                    ]);
                }

                foreach ($codes as $voucherData) {
                    $code = $voucherData['redeemCode'] ?? null;
                    $pinCode = $voucherData['pinCode'] ?? null;
                    $stockId = $voucherData['stockId'] ?? null;
                    $apiStatus = $voucherData['status'] ?? null;

                    $status = ($apiStatus === 'COMPLETED' && $code) ? 'available' : 'processing';

                    if (! $code && $status === 'processing' && $stockId) {
                        if (! $existingVouchers->has($stockId)) {
                            $toInsert[] = [
                                'code' => null,
                                'purchase_order_id' => $purchaseOrder->id,
                                'purchase_order_item_id' => $purchaseOrderItem->id,
                                'status' => $status,
                                'serial_number' => null,
                                'pin_code' => null,
                                'stock_id' => $stockId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                        continue;
                    }

                    if (! $code) {
                        continue;
                    }

                    $existingVoucher = $stockId ? $existingVouchers->get($stockId) : null;
                    $encryptedCode = $this->voucherCipherService->encryptCode($code);

                    if (! $existingVoucher) {
                        $toInsert[] = [
                            'code' => $encryptedCode,
                            'purchase_order_id' => $purchaseOrder->id,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $vouchersAdded++;
                    } else {
                        $wasProcessing = $existingVoucher->status === 'processing';
                        $existingVoucher->update([
                            'code' => $encryptedCode,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                        ]);
                        if ($wasProcessing && $status === 'available') {
                            $vouchersUpdated++;
                        }
                    }
                }
            }

            if (! empty($toInsert)) {
                Voucher::insert($toInsert);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'added' => $vouchersAdded,
            'updated' => $vouchersUpdated,
            'item_ids' => array_unique($processedItemIds),
        ];
    }
}
