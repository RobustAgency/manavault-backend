<?php

namespace App\Services\Tikkery;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Actions\Tikkery\GetOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Voucher\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class TikkeryVoucherService
{
    public function __construct(
        private GetOrder $getOrder,
        private TikkeryPlaceOrderService $tikkeryPlaceOrderService,
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    /**
     * Poll all pending Tikkery purchase orders and fetch their voucher codes.
     *
     * @return array{total_orders: int, processed_orders: int, skipped_orders: int, failed_orders: int, total_vouchers_added: int, errors: array}
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

                Log::error('Tikkery: failed to process vouchers for purchase order', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Get all Tikkery purchase order suppliers still in PROCESSING state with a transaction ID.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    private function getUnprocessedPurchaseOrders()
    {
        $purchaseOrderIds = PurchaseOrderSupplier::whereHas('supplier', function ($query) {
            $query->where('slug', 'tikkery');
        })
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNotNull('transaction_id')
            ->pluck('purchase_order_id');

        return PurchaseOrder::with(['items.digitalProduct'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();
    }

    /**
     * Process a single purchase order: call Tikkery GetOrder, store any returned codes.
     *
     * @return array{skipped: bool, vouchers_added: int, reason: string|null}
     */
    public function processPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['items.digitalProduct']);

        $result = ['skipped' => false, 'vouchers_added' => 0, 'reason' => null];

        $purchaseOrderSupplier = PurchaseOrderSupplier::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('supplier', fn ($q) => $q->where('slug', 'tikkery'))
            ->first();

        if (! $purchaseOrderSupplier || ! $purchaseOrderSupplier->transaction_id) {
            $result['skipped'] = true;
            $result['reason'] = 'No Tikkery transaction ID found';

            return $result;
        }

        $tikkeryOrderNumber = $purchaseOrderSupplier->transaction_id;

        $response = $this->getOrder->execute($tikkeryOrderNumber);

        $isCompleted = (bool) ($response['order']['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        if (! $isCompleted || empty($codes)) {
            $result['skipped'] = true;
            $result['reason'] = 'Order not yet completed or no codes returned';

            Log::info('Tikkery: order still pending, skipping', [
                'purchase_order_id' => $purchaseOrder->id,
                'tikkery_order_number' => $tikkeryOrderNumber,
                'is_completed' => $isCompleted,
                'codes_count' => count($codes),
            ]);

            return $result;
        }

        // Enrich codes with internal PO item references
        $orderItems = $purchaseOrder->items->all();
        $enrichedCodes = $this->tikkeryPlaceOrderService->enrichCodes($codes, $orderItems);

        $vouchersAdded = $this->storeVoucherCodes($purchaseOrder, $enrichedCodes);
        $result['vouchers_added'] = $vouchersAdded;

        $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

        return $result;
    }

    /**
     * Store enriched Tikkery codes as Voucher records (idempotent — skips duplicates).
     *
     * @param  array<int, array<string, mixed>>  $codes
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $codes): int
    {
        $vouchersAdded = 0;

        DB::beginTransaction();
        try {
            foreach ($codes as $code) {
                $redemptionCode = $code['redemptionCode'] ?? null;
                $serial = $code['serial'] ?? null;

                // Idempotency: skip if already stored by serial number
                if ($serial && Voucher::where('purchase_order_id', $purchaseOrder->id)
                    ->where('serial_number', $serial)
                    ->exists()) {
                    continue;
                }

                $encryptedCode = $redemptionCode
                    ? $this->voucherCipherService->encryptCode($redemptionCode)
                    : null;

                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $code['purchase_order_item_id'] ?? null,
                    'code' => $encryptedCode,
                    'serial_number' => $serial,
                    'pin_code' => $code['pin'] ?? null,
                    'status' => 'available',
                ]);

                $vouchersAdded++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tikkery: failed to store vouchers', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($vouchersAdded > 0) {
            $digitalProductIds = collect($codes)
                ->pluck('digital_product_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            event(new NewVouchersAvailable($digitalProductIds));
        }

        return $vouchersAdded;
    }

    /**
     * Store vouchers immediately from the createOrder response (called by the job when isCompleted=true).
     */
    public function storeVouchers(PurchaseOrder $purchaseOrder, array $tikkeryResponse): void
    {
        /** @var array<int, array<string, mixed>> $codes */
        $codes = $tikkeryResponse['codes'] ?? [];

        if (empty($codes)) {
            Log::warning('Tikkery: no codes in immediate createOrder response', [
                'purchase_order_id' => $purchaseOrder->id,
            ]);

            return;
        }

        $this->storeVoucherCodes($purchaseOrder, $codes);
    }
}
