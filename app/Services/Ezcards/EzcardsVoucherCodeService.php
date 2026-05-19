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

    /**
     * Process all EZ Cards purchase orders and fetch their voucher codes.
     */
    public function processAllPurchaseOrders(): bool
    {
        // Fetch all EZ Cards purchase orders that haven't been fully processed
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

    /**
     * Get all unprocessed EZ Cards purchase orders.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    private function getUnprocessedPurchaseOrders()
    {
        // Get purchase order IDs that have EZ Cards suppliers with transaction_id
        $purchaseOrderIds = PurchaseOrderSupplier::whereHas('supplier', function ($query) {
            $query->where('slug', 'ez_cards');
        })->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNotNull('transaction_id')
            ->pluck('purchase_order_id');

        return PurchaseOrder::with(['vouchers', 'items.digitalProduct'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();
    }

    /**
     * Process a purchase order by its ID to fetch and store voucher codes.
     *     */
    public function processPurchaseOrderById(PurchaseOrder $purchaseOrder): bool
    {
        $purchaseOrder->load(['vouchers', 'items.digitalProduct']);

        return $this->processPurchaseOrder($purchaseOrder);
    }

    /**
     * Process a single purchase order to fetch and store voucher codes.
     */
    public function processPurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {

        // Get the EZ Cards purchase order supplier record
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

        $transactionID = (int) $purchaseOrderSupplier->transaction_id;

        // Fetch voucher codes from EZ Cards API
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
     * Store voucher codes from API response into the database.
     *
     * @return array{added: int, updated: int, item_ids: int[]}
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): array
    {
        $vouchersAdded = 0;
        $vouchersUpdated = 0;
        $processedItemIds = [];

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

                // Process each voucher code for this product
                foreach ($codes as $voucherData) {
                    $code = $voucherData['redeemCode'] ?? null;
                    $pinCode = $voucherData['pinCode'] ?? null;
                    $stockId = $voucherData['stockId'] ?? null;
                    $apiStatus = $voucherData['status'] ?? null;

                    $status = ($apiStatus === 'COMPLETED' && $code) ? 'available' : 'processing';

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
                        }

                        continue;
                    }

                    // Skip if no code and not processing
                    if (! $code) {
                        continue;
                    }

                    // Check if voucher already exists by stockId only
                    $existingVoucher = $stockId
                        ? Voucher::where('purchase_order_id', $purchaseOrder->id)->where('stock_id', $stockId)->first()
                        : null;

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
                        $wasProcessing = $existingVoucher->status === 'processing';
                        $existingVoucher->update([
                            'code' => $code,
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
